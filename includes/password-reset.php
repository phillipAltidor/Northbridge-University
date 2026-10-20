<?php
declare(strict_types=1);

function reset_credential_version(array $account): string
{
    return hash('sha256', json_encode([$account['user_Password'], $account['user_Email'], $account['user_Type']], JSON_THROW_ON_ERROR));
}

function reset_password_error(string $password, string $confirmation): string
{
    if (strlen($password) < 12 || strlen($password) > 72) return 'Use a password between 12 and 72 bytes long.';
    if ($password !== $confirmation) return 'The passwords do not match.';
    return '';
}

final class PasswordResetService
{
    private Closure $deliver;
    private Closure $notify;

    public function __construct(private PDO $pdo, callable $deliver, callable $notify)
    {
        $this->deliver = Closure::fromCallable($deliver);
        $this->notify = Closure::fromCallable($notify);
    }

    public function issue(string $email, string $ip, string $role = ''): array
    {
        $emailHash = hash('sha256', strtolower(trim($email)));
        $ipHash = hash('sha256', $ip);
        $id = bin2hex(random_bytes(32));
        $code = sprintf('%06d', random_int(0, 999999));
        $codeHash = password_hash($code, PASSWORD_DEFAULT);
        $now = time();
        $lock = 'reset:' . $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $statement = $this->pdo->prepare('SELECT GET_LOCK(?, 5)');
        $statement->execute([$lock]);
        if ((int) $statement->fetchColumn() !== 1) throw new RuntimeException('Reset service busy.');
        $account = false;
        try {
            $this->pdo->beginTransaction();
            // Serialize request limits across sessions, without holding a lock during SMTP.
            $statement = $this->pdo->prepare('SELECT created_At FROM Password_Reset WHERE email_Hash = ? AND created_At >= ? ORDER BY created_At DESC');
            $statement->execute([$emailHash, gmdate('Y-m-d H:i:s', $now - 3600)]);
            $recent = $statement->fetchAll(PDO::FETCH_COLUMN);
            $statement = $this->pdo->prepare('SELECT COUNT(*) FROM Password_Reset WHERE ip_Hash = ? AND created_At >= ?');
            $statement->execute([$ipHash, gmdate('Y-m-d H:i:s', $now - 3600)]);
            if (count($recent) >= 6 || ($recent && $recent[0] >= gmdate('Y-m-d H:i:s', $now - 60)) || (int) $statement->fetchColumn() >= 20) {
                $this->pdo->rollBack();
                return ['id' => null, 'limited' => true];
            }
            $statement = $this->pdo->prepare('SELECT * FROM Login WHERE user_Email = ? LIMIT 1');
            $statement->execute([trim($email)]);
            $account = $statement->fetch();
            if ($account && $role !== '' && $account['user_Type'] !== $role) $account = false;
            $statement = $this->pdo->prepare('INSERT INTO Password_Reset (request_ID, user_ID, email_Hash, ip_Hash, credential_Hash, code_Hash, created_At, expires_At) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$id, $account ? $account['user_ID'] : null, $emailHash, $ipHash, $account ? reset_credential_version($account) : null, $codeHash, gmdate('Y-m-d H:i:s', $now), gmdate('Y-m-d H:i:s', $now + 600)]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        } finally {
            $this->pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lock]);
        }
        if ($account) {
            try {
                ($this->deliver)($account['user_Email'], $code);
            } catch (Throwable $error) {
                $this->pdo->prepare('UPDATE Password_Reset SET consumed_At = ?, code_Hash = NULL WHERE request_ID = ?')->execute([gmdate('Y-m-d H:i:s'), $id]);
                error_log('Password reset delivery failed; reference ' . $id);
            }
        }
        return ['id' => $id, 'limited' => false];
    }

    public function verify(string $id, string $code): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id)) return null;
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('SELECT * FROM Password_Reset WHERE request_ID = ? FOR UPDATE');
            $statement->execute([$id]);
            $request = $statement->fetch();
            if (!$request || $request['consumed_At'] || $request['verified_At'] || $request['expires_At'] <= gmdate('Y-m-d H:i:s') || (int) $request['attempts'] >= 5) {
                $this->pdo->rollBack();
                return null;
            }
            $matches = password_verify($code, (string) $request['code_Hash']);
            $statement = $this->pdo->prepare('SELECT * FROM Login WHERE user_ID = ?');
            $statement->execute([$request['user_ID']]);
            $account = $statement->fetch();
            if (!preg_match('/^[0-9]{6}$/D', $code) || !$matches || !$account || !hash_equals((string) $request['credential_Hash'], reset_credential_version($account))) {
                $attempts = (int) $request['attempts'] + 1;
                $this->pdo->prepare('UPDATE Password_Reset SET attempts = ?, consumed_At = ? WHERE request_ID = ?')->execute([$attempts, $attempts >= 5 ? gmdate('Y-m-d H:i:s') : null, $id]);
                $this->pdo->commit();
                return null;
            }
            $grant = bin2hex(random_bytes(32));
            $this->pdo->prepare('UPDATE Password_Reset SET verified_At = ?, expires_At = ?, grant_Hash = ?, code_Hash = NULL WHERE request_ID = ?')->execute([gmdate('Y-m-d H:i:s'), min($request['expires_At'], gmdate('Y-m-d H:i:s', time() + 300)), hash('sha256', $grant), $id]);
            $this->pdo->commit();
            return $grant;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    public function complete(string $id, string $grant, string $password): bool
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $id) || !preg_match('/^[a-f0-9]{64}$/D', $grant) || reset_password_error($password, $password) !== '') return false;
        $statement = $this->pdo->prepare('SELECT user_ID FROM Password_Reset WHERE request_ID = ?');
        $statement->execute([$id]);
        $userID = $statement->fetchColumn();
        if (!$userID) return false;
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $this->pdo->beginTransaction();
        try {
            // Lock the account first so two verified requests cannot both change it.
            $statement = $this->pdo->prepare('SELECT * FROM Login WHERE user_ID = ? FOR UPDATE');
            $statement->execute([$userID]);
            $account = $statement->fetch();
            $statement = $this->pdo->prepare('SELECT * FROM Password_Reset WHERE request_ID = ? FOR UPDATE');
            $statement->execute([$id]);
            $request = $statement->fetch();
            if (!$account || !$request || !$request['verified_At'] || $request['consumed_At'] || $request['expires_At'] <= gmdate('Y-m-d H:i:s') || !hash_equals((string) $request['grant_Hash'], hash('sha256', $grant)) || !hash_equals((string) $request['credential_Hash'], reset_credential_version($account))) {
                $this->pdo->rollBack();
                return false;
            }
            $this->pdo->prepare('UPDATE Login SET user_Password = ?, no_Of_Tries = 0, lock_var = 0 WHERE user_ID = ?')->execute([$newHash, $userID]);
            $this->pdo->prepare('UPDATE Password_Reset SET consumed_At = ?, code_Hash = NULL, grant_Hash = NULL WHERE user_ID = ?')->execute([gmdate('Y-m-d H:i:s'), $userID]);
            $this->pdo->commit();
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
        try { ($this->notify)($account['user_Email']); } catch (Throwable $error) { error_log('Password reset confirmation email failed.'); }
        return true;
    }
}
