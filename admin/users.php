<?php

require_once '../config/database.php';
require_once '../includes/layout.php';
require_once '../includes/functions.php';

require_role(['Admin']);

$adminID = (int) $_SESSION['user_id'];


// Get the admin's access level
$admin = one(
    $pdo,
    "SELECT security_Level
     FROM Admin
     WHERE user_ID = ?",
    [$adminID]
);

$securityLevel = $admin['security_Level'] ?? 'Read-Only';


// Search
$search = trim($_GET['search'] ?? '');


// Update a user
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($securityLevel !== 'Full-Access') {

        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'You have read-only access and cannot update users.'
        ];

        header('Location: users.php');
        exit;
    }


    $userID = (int) ($_POST['user_id'] ?? 0);

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');


    $oldUser = one(
        $pdo,
        "SELECT
            first_Name,
            last_Name,
            city,
            state
         FROM User
         WHERE user_ID = ?",
        [$userID]
    );


    if ($oldUser) {

        try {

            $pdo->beginTransaction();


            $sql = "UPDATE User
                    SET
                        first_Name = ?,
                        last_Name = ?,
                        city = ?,
                        state = ?
                    WHERE user_ID = ?";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $firstName,
                $lastName,
                $city,
                $state,
                $userID
            ]);


            $changes = [
                'first_Name' => [$oldUser['first_Name'], $firstName],
                'last_Name' => [$oldUser['last_Name'], $lastName],
                'city' => [$oldUser['city'], $city],
                'state' => [$oldUser['state'], $state]
            ];


            foreach ($changes as $field => $values) {

                $oldValue = (string) $values[0];
                $newValue = (string) $values[1];

                if ($oldValue !== $newValue) {

                    $sql = "INSERT INTO User_Update_Log
                                (
                                    user_ID,
                                    changed_By_User_ID,
                                    changed_At,
                                    changed_Field,
                                    old_Value,
                                    new_Value,
                                    user_Request_Reference
                                )
                            VALUES (?, ?, NOW(), ?, ?, ?, ?)";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $userID,
                        $adminID,
                        $field,
                        $oldValue,
                        $newValue,
                        'Admin portal update'
                    ]);
                }
            }


            $pdo->commit();


            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'User information updated.'
            ];

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }


            $_SESSION['flash'] = [
                'type' => 'error',
                'message' => 'The user could not be updated.'
            ];
        }
    }


    header('Location: users.php');
    exit;
}


// Get one user for editing
$editUser = null;

if (isset($_GET['edit'])) {

    $editID = (int) $_GET['edit'];

    $editUser = one(
        $pdo,
        "SELECT
            user_ID,
            first_Name,
            last_Name,
            user_Type,
            city,
            state
         FROM User
         WHERE user_ID = ?",
        [$editID]
    );
}


// Get users
if ($search !== '') {

    $searchValue = '%' . $search . '%';

    $sql = "SELECT
                user_ID,
                first_Name,
                last_Name,
                user_Type,
                city,
                state
            FROM User
            WHERE
                CAST(user_ID AS CHAR) LIKE ?
                OR first_Name LIKE ?
                OR last_Name LIKE ?
            ORDER BY user_ID
            LIMIT 25";

    $users = all_rows(
        $pdo,
        $sql,
        [
            $searchValue,
            $searchValue,
            $searchValue
        ]
    );

} else {

    $sql = "SELECT
                user_ID,
                first_Name,
                last_Name,
                user_Type,
                city,
                state
            FROM User
            ORDER BY user_ID
            LIMIT 25";

    $users = all_rows(
        $pdo,
        $sql
    );
}


page_start(
    'Users',
    'Admin',
    'users.php'
);

flash();

?>


<div class="card">

    <p>
        <strong>Admin Access:</strong>
        <?= e($securityLevel) ?>
    </p>

    <form method="get">

        <label for="search">
            <strong>Search Users</strong>
        </label>

        <input
            type="text"
            name="search"
            id="search"
            value="<?= e($search) ?>"
            placeholder="ID, first name, or last name"
        >

        <button
            type="submit"
            class="btn small"
        >
            Search
        </button>

        <?php if ($search !== ''): ?>

            <a
                href="users.php"
                class="btn light small"
            >
                Clear
            </a>

        <?php endif; ?>

    </form>

</div>


<?php if (
    $editUser &&
    $securityLevel === 'Full-Access'
): ?>

    <div class="card">

        <h2>
            Edit User <?= e($editUser['user_ID']) ?>
        </h2>

        <form method="post">

            <input
                type="hidden"
                name="user_id"
                value="<?= e($editUser['user_ID']) ?>"
            >


            <label>
                First Name
            </label>

            <input
                type="text"
                name="first_name"
                value="<?= e($editUser['first_Name']) ?>"
                required
            >


            <label>
                Last Name
            </label>

            <input
                type="text"
                name="last_name"
                value="<?= e($editUser['last_Name']) ?>"
                required
            >


            <label>
                City
            </label>

            <input
                type="text"
                name="city"
                value="<?= e($editUser['city']) ?>"
            >


            <label>
                State
            </label>

            <input
                type="text"
                name="state"
                value="<?= e($editUser['state']) ?>"
            >


            <br><br>

            <button
                type="submit"
                class="btn"
            >
                Save Changes
            </button>

            <a
                href="users.php"
                class="btn light"
            >
                Cancel
            </a>

        </form>

    </div>

<?php endif; ?>


<div class="table-wrap">

    <table>

        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Role</th>
            <th>Location</th>

            <?php if ($securityLevel === 'Full-Access'): ?>
                <th>Action</th>
            <?php endif; ?>

        </tr>


        <?php foreach ($users as $user): ?>

            <tr>

                <td>
                    <?= e($user['user_ID']) ?>
                </td>

                <td>
                    <?= e(
                        $user['first_Name'] . ' ' .
                        $user['last_Name']
                    ) ?>
                </td>

                <td>
                    <?= e($user['user_Type']) ?>
                </td>

                <td>
                    <?= e(
                        $user['city'] . ', ' .
                        $user['state']
                    ) ?>
                </td>


                <?php if ($securityLevel === 'Full-Access'): ?>

                    <td>

                        <a
                            href="users.php?edit=<?= e($user['user_ID']) ?>"
                            class="btn small"
                        >
                            Edit
                        </a>

                    </td>

                <?php endif; ?>

            </tr>

        <?php endforeach; ?>

    </table>

</div>


<div class="card">

    <p class="muted">
        Showing up to 25 users. Use the search box to find another user.
    </p>

</div>


<?php

page_end();

?>