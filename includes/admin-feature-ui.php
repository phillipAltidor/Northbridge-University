<?php
function af_field(PDO $db,string $field,mixed $value,string $mode,bool $selecting=false): void {
 $label=af_labels()[$field];$options=af_options($db,$field);
 if (($mode==='create-course'&&$field==='course_ID')||($mode==='create-building'&&$field==='Bldg_ID')||($mode==='create-room'&&$field==='room_ID')) $options=null;
 ?><div class="af-field"><label for="<?= e($field) ?>"><?= e($label) ?></label><?php
 if ($options!==null) {
  ?><select id="<?= e($field) ?>" name="<?= e($field) ?>" required><option value="">Choose <?= e(strtolower($label)) ?></option><?php foreach ($options as $id=>$text): ?><option value="<?= e($id) ?>" <?= (string)$id===(string)$value?'selected':'' ?>><?= e($text) ?></option><?php endforeach; ?></select><?php
 } elseif (in_array($field,['course_DSC','reason'],true)) { ?><textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="3" maxlength="500" required><?= e($value) ?></textarea><?php }
 else { $numeric=in_array($field,['course_Credits','section_No','room_Number','capacity'],true);$max=['course_ID'=>20,'Bldg_ID'=>8,'room_ID'=>20,'course_Name'=>255,'Bldg_Name'=>255,'building_Usage'=>255][$field]??100;
 ?><input id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= $numeric?'number':'text' ?>" <?= $numeric?'min="1" max="2147483647"':'maxlength="'.$max.'"' ?> value="<?= e($value) ?>" required><?php } ?></div><?php
}
function af_summary(array $row): void {
 if (!$row) {echo '<p>No existing records.</p>';return;}
 echo '<dl class="af-summary">';foreach ($row as $k=>$v) if (!is_array($v)) echo '<div><dt>'.e(af_labels()[$k]??str_replace('_',' ',$k)).'</dt><dd>'.e($v??'Not recorded').'</dd></div>';echo '</dl>';
 foreach ($row as $k=>$v) if (is_array($v)) {echo '<details open><summary>'.e(ucfirst($k)).'</summary>';if (array_is_list($v)) {foreach ($v as $r) af_summary($r);if (!$v) echo '<p>None recorded.</p>';}else af_summary($v);echo '</details>';}
}
