<?php
?>
<link rel="stylesheet" href="<?= e(app_url('assets/css/admin-features.css')) ?>">
<div class="af-workspace">
<p class="af-intro">Manage university records. Load an existing record to review its current information before saving.</p>
<?php if ($notice): ?><div class="af-notice" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error): ?><div class="af-error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$canWrite): ?><div class="af-notice">Your account has read-only access. You can view records, but cannot save changes.</div><?php endif; ?>
<?php if ($selectors): ?><section class="af-card"><h2>1. Select a record</h2><form method="get" class="af-form">
<?php foreach ($selectors as $k) af_field($pdo,$k,$selected[$k],$adminFeature,true); ?>
<div class="af-actions"><button type="submit">Load record</button></div></form></section><?php endif; ?>
<?php if ($ready && (!$selectors || $snapshot)): ?>
<?php if ($snapshot): ?><section class="af-card"><h2>Current information</h2><?php af_summary($snapshot); ?></section><?php endif; ?>
<section class="af-card"><h2><?= $selectors?'2. Review and save':'Enter information' ?></h2>
<?php if (str_contains($adminFeature,'student-section')): ?><p>Add/drop dates: Fall 2026, August 24–December 16; Spring 2027, January 25–May 18 (New York time).</p><?php endif; ?>
<?php if (str_contains($adminFeature,'faculty-section')): ?><p>Every section already requires an instructor in the current database. Selecting a new faculty member replaces its current assignment after department, workload and conflict checks.</p><?php endif; ?>
<?php if (str_contains($adminFeature,'requirements')): ?><p>Each entry adds or updates one required course and its minimum grade. Reviewed, versioned curricula are protected from changes to these legacy rows.</p><?php endif; ?>
<?php if ($adminFeature==='declare-major'): ?><p>Adds an additional declared major. The student’s primary major stays listed separately.</p><?php endif; ?>
<form method="post" action="<?= e(app_url($path).($selected?'?'.http_build_query($selected):'')) ?>" class="af-form">
<input type="hidden" name="form_token" value="<?= e($_SESSION['admin_feature_token']) ?>"><input type="hidden" name="version" value="<?= e($version) ?>">
<?php foreach ($fields as $k) af_field($pdo,$k,$values[$k]??'',$adminFeature); ?>
<div class="af-actions"><button type="submit" <?= !$canWrite?'disabled':'' ?>><?= $adminFeature==='drop-student-section'?'Drop selected enrollment':'Save changes' ?></button><a href="<?= e(app_url($path)) ?>">Start over</a></div>
</form></section><?php endif; ?>
</div>

