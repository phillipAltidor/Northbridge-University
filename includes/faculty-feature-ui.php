<?php
declare(strict_types=1);
function faculty_select(string $name, string $label, array $options, $selected='', bool $required=false): void {
 echo '<div class="form-group"><label for="ff-'.e($name).'">'.e($label).'</label><select id="ff-'.e($name).'" name="'.e($name).'"'.($required?' required':'').'><option value="">'.($required?'Select an option':'All / choose an option').'</option>';
 foreach ($options as $value=>$text) echo '<option value="'.e($value).'"'.((string)$value===(string)$selected?' selected':'').'>'.e($text).'</option>';
 echo '</select></div>';
}
function faculty_input(string $name,string $label,string $value='',string $type='text',int $length=100,bool $required=false): void {
 echo '<div class="form-group"><label for="ff-'.e($name).'">'.e($label).'</label><input id="ff-'.e($name).'" name="'.e($name).'" type="'.e($type).'" maxlength="'.$length.'" value="'.e($type==='password'?'':$value).'"'.($required?' required':'').($type==='password'?' autocomplete="'.($name==='current_password'?'current-password':'new-password').'"':'').'></div>';
}
function faculty_table(array $rows,array $columns,string $caption,?callable $actions=null): void {
 if (!$rows) { echo '<p class="ff-empty">No matching records found.</p>'; return; }
 echo '<div class="ff-scroll" tabindex="0" role="region" aria-label="'.e($caption).'"><table><caption>'.e($caption).'</caption><thead><tr>';
 foreach ($columns as $label) echo '<th scope="col">'.e($label).'</th>';
 if ($actions) echo '<th scope="col">Open</th>';
 echo '</tr></thead><tbody>';
 foreach ($rows as $row) {
  echo '<tr>'; foreach ($columns as $key=>$label) echo '<td>'.e($row[$key]??'—').'</td>';
  if ($actions) { echo '<td>'; $actions($row); echo '</td>'; } echo '</tr>';
 } echo '</tbody></table></div>';
}
function faculty_link(string $mode,array $query,string $label): void {
 echo '<a href="'.e(app_url('faculty/'.$mode.'.php').'?'.http_build_query($query)).'">'.e($label).'</a> ';
}
function faculty_rule_tree($value, string $label=''): void {
 // Preserve choices, concentrations, thresholds and policies as a readable tree,
 // rather than flattening reviewed OR alternatives into required-course rows.
 if (!is_array($value)) {
  $text=is_bool($value)?($value?'Yes':'No'):(string)$value;
  echo '<li>'.($label!==''?'<strong>'.e(faculty_rule_label($label)).':</strong> ':'').e($text).'</li>'; return;
 }
 echo '<li>'.($label!==''?'<strong>'.e(faculty_rule_label($label)).'</strong>':'').'<ul>';
 foreach ($value as $key=>$child) {
  if ($key==='type') {
   $types=['all-courses'=>'Complete all listed courses','choose-distinct-courses'=>'Choose distinct courses from the options','choose-groups'=>'Choose from the following groups'];
   faculty_rule_tree($types[$child]??str_replace('-',' ',$child),'Requirement');
  } else faculty_rule_tree($child,is_int($key)?'':(string)$key);
 } echo '</ul></li>';
}
function faculty_rule_label(string $label): string { return ucfirst(str_replace(['_','-'],' ',preg_replace('/([a-z])([A-Z])/','$1 $2',$label))); }
