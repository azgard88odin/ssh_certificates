<?php
//Please note that this is a very simple script only for purposes of this lab which is in a contained environment
//Please add security and data validation checks in production tools/scripts
$target_dir = '/var/www/html/uploads';
$target_file = $target_dir . basename($_FILES['file']['name']);
		
move_uploaded_file($_FILES['file']['tmp_name'], $target_file);

?>
