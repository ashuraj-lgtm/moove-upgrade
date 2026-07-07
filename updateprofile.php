<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->libdir.'/authlib.php');
global $CFG, $DB, $USER;
$systemcontext   = context_system::instance();
$filemanageroptions = array('maxbytes'       => $CFG->maxbytes,
                             'subdirs'        => 0,
                             'maxfiles'       => 1,
                             'accepted_types' => 'web_image');

if (\core\session\manager::is_loggedinas()) {
	echo json_encode(['error' => 1 , 'message' => get_string('cannotcallscript') , 'data'=> null ]);
}else{

	if (!isloggedin() or isguestuser()) {
		if (isguestuser()) {
			echo json_encode(['error' => 1 , 'message' => get_string('guestnoeditprofile'), 'data'=> null ]);
		}
		
	}else{
		$userid = optional_param('id', $USER->id, PARAM_INT);
		if (!$user = $DB->get_record('user', array('id' => $userid))) {
		    echo json_encode(['error' => 1 , 'message' => get_string('invaliduserid'), 'data'=> null ]);
		}
		$userauth = get_auth_plugin($USER->auth);
		/* user logged in */
		$personalcontext = context_user::instance($USER->id);
		if (isset($_POST['password']) && !empty($_POST['password'])) {
			$password = $_POST['password'];
			if (!$userauth->can_edit_profile()) {
			    echo json_encode(['error' => 1 , 'message' => get_string('noprofileedit', 'auth') , 'data'=> null ]);
			}else{
				if (!$userauth->can_change_password()) {
			    echo json_encode(['error' => 1 , 'message' => get_string('nopasswordchange', 'auth') , 'data'=> null ]);
			}else{
				/* Can change Password */
				if (!$userauth->user_update_password($USER, $password)) {
					\core\event\user_updated::create_from_userid($USER->id)->trigger();
			        echo json_encode(['error' => 1 , 'message' => get_string('errorpasswordupdate', 'auth') , 'data'=> null ]);
			    }else{
			    	echo json_encode(['error' => 0 , 'message' => get_string('passwordchanged') , 'data'=> null ]);
			    }
			}
		}
			
		}
	if (!$userauth->can_edit_profile()) {
			 echo json_encode(['error' => 1 , 'message' => get_string('noprofileedit', 'auth') , 'data'=> null ]);
		}else{
			if (isset($_POST['emailaddress']) and $user->email != $_POST['emailaddress'] && !has_capability('moodle/user:update', $systemcontext)) {
            $a = new stdClass();
            $emailchangedkey = random_string(20);
            set_user_preference('newemail', $_POST['emailaddress'], $user->id);
            set_user_preference('newemailkey', $emailchangedkey, $user->id);
            set_user_preference('newemailattemptsleft', 3, $user->id);

            $a->newemail = $emailchanged = $_POST['emailaddress'];
            $a->oldemail = $_POST['emailaddress'] = $user->email;
            echo json_encode(['error' => 0 , 'message' => get_string('auth_changingemailaddress', 'auth', $a) , 'data'=> null ]);
		}
	}

	if ($userauth->can_edit_profile()) {
		if (isset($_POST['bio']) && !empty($_POST['bio']) &&  $user->description != $_POST['bio']) {
			if($DB->set_field('user', 'description', $_POST['bio'], array( 'id' => $USER->id))){
				\core\event\user_updated::create_from_userid($USER->id)->trigger();
				echo json_encode(['error' => 0 , 'message' => 'User bio updated', 'data'=> null ]);
			}else{
				echo json_encode(['error' => 1 , 'message' => 'Cannot update bio' , 'data'=> null ]);
			}
		}
	}
		if ($userauth->can_edit_profile()) {
		if (isset($_POST['firstname']) && !empty($_POST['firstname']) && trim($user->firstname) != trim($_POST['firstname']) ) {
			if($DB->set_field( 'user', 'firstname', trim($_POST['firstname'], " "), array( 'id' => $USER->id))){
				\core\event\user_updated::create_from_userid($USER->id)->trigger();
				echo json_encode(['error' => 0 , 'message' => 'User firstname updated', 'data'=> null ]);
			}else{
				echo json_encode(['error' => 1 , 'message' => 'Cannot update firstname' , 'data'=> null ]);
			}
		}
	}
	if ($userauth->can_edit_profile()) {
		if (isset($_POST['lastname']) && !empty($_POST['lastname']) && trim($user->lastname) != trim($_POST['lastname'])) {
			if($DB->set_field( 'user', 'lastname', trim($_POST['lastname']), array( 'id' => $USER->id))){
				\core\event\user_updated::create_from_userid($USER->id)->trigger();
				echo json_encode(['error' => 0 , 'message' => 'User lastname updated ', 'data'=> null ]);
			}else{
				echo json_encode(['error' => 1 , 'message' => 'Cannot update lastname' , 'data'=> null ]);
			}
		}
	}
	if ($userauth->can_edit_profile()) {
		if (isset($_POST['username']) && !empty($_POST['username']) && trim($user->username) != trim($_POST['username'])) {
			if($DB->set_field( 'user', 'username', trim($_POST['username']), array( 'id' => $USER->id))){
				\core\event\user_updated::create_from_userid($USER->id)->trigger();
				echo json_encode(['error' => 0 , 'message' => 'User username updated ', 'data'=> null ]);
			}else{
				echo json_encode(['error' => 1 , 'message' => 'Cannot update username' , 'data'=> null ]);
			}
		}
	}

	/* start */
	 // Update user picture.

	if (isset($_FILES["file"]) && !empty($_FILES["file"])){
		if (empty($CFG->disableuserimages)) {
				$tempfilename = substr( microtime(), 0, 10 ) . '.tmp';
				$templfolder = $CFG->tempdir . '/filestorage';	
				if ( !file_exists( $templfolder ) ) {
				  mkdir( $templfolder, $CFG->directorypermissions );
				}
				$tempfile = $templfolder . '/' . $tempfilename;	
				if ( copy( $_FILES["file"]["tmp_name"], $tempfile ) ) {			
				  require_once("$CFG->libdir/gdlib.php");
				  $usericonid = process_new_icon( context_user::instance( $USER->id, MUST_EXIST ), 'user', 'icon', 0, $tempfile );
				  if ($usericonid) {
				    if($DB->set_field( 'user', 'picture', $usericonid, array( 'id' => $USER->id))){
				    	/* profile picture uploded */
				    	echo json_encode(['error' => 0 , 'message' => 'Profile picture Updated', 'data'=> null ]);
				    }else{
				    	/* some error */
				    	echo json_encode(['error' => 1 , 'message' => 'Profile picture not Updated', 'data'=> null ]);
				    }
				  }else{
				  	/* some error */
				  	echo json_encode(['error' => 1 , 'message' => ' Some Error while creating user icon ', 'data'=> null ]);
				  }			
				  unset( $tempfile);
				}						
		}
	}

	/* end */
	}
}
die;