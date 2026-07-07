<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once("$CFG->libdir/enrollib.php");
global $CFG, $DB, $USER;

if (\core\session\manager::is_loggedinas()) {
	echo json_encode(['error' => 1 , 'message' => get_string('cannotcallscript') , 'data'=> null ]);
}else{
	if (isloggedin() && !isguestuser()) {
		if (isset($_POST['courseid']) && !empty($_POST['courseid'])) {
			$userid = $USER->id;

			$instances = $DB->get_records('enrol', array('courseid' => $_POST['courseid']));

			foreach ($instances as $instance) {
			    $plugin = enrol_get_plugin($instance->enrol);
			    if ($plugin->unenrol_user($instance, $userid)) {
					echo json_encode(['error' => 0 , 'message' => "Unerolled from Course", 'data'=> null]);    	
			    }else{
			    	echo json_encode(['error' => 0 , 'message' => "Unerolled from Course", 'data'=> null]);
			    }
			}

		}else{
			echo json_encode(['error' => 1 , 'message' => "All field required", 'data'=> null ]);
		}
	}
}
die;