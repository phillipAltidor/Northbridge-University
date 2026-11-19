<?php
function af_definitions(): array {
 return [
 'create-course'=>['Create Course',[],['course_ID','course_Name','dept_ID','course_Credits','course_Type','course_DSC']],
 'update-course'=>['Update Course',['course_ID'],['course_Name','dept_ID','course_Credits','course_Type','course_DSC']],
 'add-prerequisites'=>['Add Course Prerequisites',['course_ID'],['prerequisite_course_ID','min_Grade_Req']],
 'create-section'=>['Create Course Section',[],['course_ID','semester_ID','section_No','faculty_ID','time_Slot_ID','room_ID']],
 'update-section'=>['Update Course Section',['CRN'],['course_ID','section_No','faculty_ID','time_Slot_ID','room_ID']],
 'add-faculty-section'=>['Add Faculty to Course Section',['CRN'],['faculty_ID']],
 'update-faculty-section'=>['Update Faculty for Course Section',['CRN'],['faculty_ID']],
 'create-major'=>['Create Major',[],['major_Name','dept_ID']],
 'update-major'=>['Update Major',['major_ID'],['major_Name','dept_ID']],
 'create-minor'=>['Create Minor',[],['minor_Name','dept_ID']],
 'update-minor'=>['Update Minor',['minor_ID'],['minor_Name','dept_ID']],
 'create-major-requirements'=>['Create Major Requirements',['major_ID'],['course_ID','min_Grade_Req']],
 'update-major-requirements'=>['Update Major Requirements',['major_ID'],['course_ID','min_Grade_Req']],
 'create-minor-requirements'=>['Create Minor Requirements',['minor_ID'],['course_ID','min_Grade_Req']],
 'update-minor-requirements'=>['Update Minor Requirements',['minor_ID'],['course_ID','min_Grade_Req']],
 'add-student-section'=>['Add Student to Course Section',['student_ID','CRN'],[]],
 'drop-student-section'=>['Drop Student from Course Section',['student_ID','CRN'],['reason']],
 'update-student-grade'=>['Update Student Grade',['student_ID','CRN'],['Grade','reason']],
 'change-grade'=>['Change Previously Recorded Grade',['student_ID','CRN'],['Grade','reason']],
 'declare-major'=>['Declare Student Major',['student_ID'],['major_ID']],
 'add-minor'=>['Add Student Minor',['student_ID'],['minor_ID']],
 'assign-advisor'=>['Assign Advisor to Student',['student_ID'],['faculty_ID']],
 'create-building'=>['Create Building',[],['Bldg_ID','Bldg_Name','building_Usage']],
 'update-building'=>['Update Building',['Bldg_ID'],['Bldg_Name','building_Usage']],
 'create-room'=>['Create Room in Building',[],['Bldg_ID','room_ID','room_Number','room_Type','capacity']],
 ];
}
function af_labels(): array {return ['course_ID'=>'Course','course_Name'=>'Course name','dept_ID'=>'Department','course_Credits'=>'Credits','course_Type'=>'Academic level','course_DSC'=>'Description','prerequisite_course_ID'=>'Required course','min_Grade_Req'=>'Minimum grade','semester_ID'=>'Semester','section_No'=>'Section number','faculty_ID'=>'Faculty member','time_Slot_ID'=>'Meeting days and period','room_ID'=>'Room','CRN'=>'Course section','major_ID'=>'Major','minor_ID'=>'Minor','major_Name'=>'Major name','minor_Name'=>'Minor name','student_ID'=>'Student','Grade'=>'Final grade','reason'=>'Reason / request reference','Bldg_ID'=>'Building','Bldg_Name'=>'Building name','building_Usage'=>'Building usage','room_Number'=>'Room number','room_Type'=>'Room type','capacity'=>'Seats, workstations or desks'];}
function af_options(PDO $db,string $field): ?array {
 $sql=[
 'course_ID'=>"SELECT course_ID,CONCAT(course_ID,' · ',course_Name) FROM Course ORDER BY course_ID",
 'prerequisite_course_ID'=>"SELECT course_ID,CONCAT(course_ID,' · ',course_Name) FROM Course ORDER BY course_ID",
 'dept_ID'=>'SELECT dept_ID,dept_Name FROM Department ORDER BY dept_Name',
 'major_ID'=>'SELECT major_ID,major_Name FROM Major ORDER BY major_Name',
 'minor_ID'=>'SELECT minor_ID,minor_Name FROM Minor ORDER BY minor_Name',
 'semester_ID'=>'SELECT semester_ID,semester_Name FROM Semester ORDER BY semester_ID DESC',
 'faculty_ID'=>"SELECT f.faculty_ID,CONCAT(u.last_Name,', ',u.first_Name,' · ',f.faculty_ID,' · ',f.faculty_Type) FROM Faculty f JOIN User u ON u.user_ID=f.faculty_ID ORDER BY u.last_Name,u.first_Name",
 'student_ID'=>"SELECT s.student_ID,CONCAT(u.last_Name,', ',u.first_Name,' · ',s.student_ID) FROM Student s JOIN User u ON u.user_ID=s.student_ID ORDER BY u.last_Name,u.first_Name",
 'CRN'=>"SELECT s.CRN,CONCAT(s.CRN,' · ',c.course_ID,' · section ',s.section_No,' · ',t.semester_Name) FROM Course_Section s JOIN Course c ON c.course_ID=s.course_ID JOIN Semester t ON t.semester_ID=s.semester_ID ORDER BY s.semester_ID DESC,c.course_ID,s.section_No",
 'time_Slot_ID'=>"SELECT time_Slot_ID,CONCAT(time_Slot_Day,' · ',time_Slot_Period) FROM Time_Slot ORDER BY time_Slot_ID",
 'room_ID'=>"SELECT r.room_ID,CONCAT(b.Bldg_Name,' · ',r.room_Number,' · ',r.room_Type) FROM Room r JOIN Building b ON b.Bldg_ID=r.Bldg_ID ORDER BY b.Bldg_Name,r.room_Number",
 'Bldg_ID'=>'SELECT Bldg_ID,Bldg_Name FROM Building ORDER BY Bldg_Name',
 ];
 if (isset($sql[$field])) return $db->query($sql[$field])->fetchAll(PDO::FETCH_KEY_PAIR);
 if ($field==='course_Type') return ['Undergraduate'=>'Undergraduate','Graduate'=>'Graduate'];
 if ($field==='room_Type') return ['Lecture'=>'Lecture','Lab'=>'Lab','Office'=>'Office'];
 if (in_array($field,['Grade','min_Grade_Req'],true)) return array_combine(array_keys(Northbridge\Academic\Transcript::POINTS),array_keys(Northbridge\Academic\Transcript::POINTS));
 return null;
}
