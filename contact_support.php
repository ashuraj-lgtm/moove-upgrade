<?php 
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $CFG, $DB, $USER;
	$feedmessage = "";
	$feedsubject = "";
	$fileuplodaded = false;
	$file_filename = null;

	if (isset($_POST['message']) && !empty($_POST['message']) && isset($_POST['subject']) && !empty($_POST['subject']) && isset($_FILES['file']) ) {
		$feedmessage = $_POST['message'];
		$feedsubject = $_POST['subject'];
	}else{
		echo json_encode(['error' => 1 , 'message' => "All field required", 'data'=> null ]);
	}
	if ($CFG->dataroot) {
		if (!is_dir($CFG->dataroot.'/contactsupportform')) {
		    mkdir($CFG->dataroot.'/contactsupportform', 0777, true);
		}
		 $target_dir = $CFG->dataroot.'/contactsupportform/';
		 $date = date('m/d/Yh:i:sa', time());
		 $rand=rand(10000,99999);
		 $encname=$date.$rand;
		 $banner=$_FILES['file']['name']; 
		 $expbanner=explode('.',$banner);
		 $bannerexptype=$expbanner[1];
		 $bannername=md5($encname).'.'.$bannerexptype;
		 $bannerpath=$target_dir.$bannername;
		$target_file = $target_dir . basename($_FILES["file"]["name"]);
		$uploadOk = 1;
		$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
		// Allow certain file formats
		if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
			echo json_encode(['error' => 1 , 'message' => "Sorry, only JPG, JPEG, PNG & GIF files are allowed.", 'data'=> null ]);
		  $uploadOk = 0;
		  die;
		}
		if ($uploadOk == 0) {
		  echo json_encode(['error' => 1 , 'message' => "Sorry, your file was not uploaded.", 'data'=> null ]);
		} else {
		  if (move_uploaded_file($_FILES["file"]["tmp_name"], $bannerpath)) {
		    $fileuplodaded = true;
		    $file_filename = $bannerpath;

		    	if ($feedmessage !== "" && $feedsubject !=="" && $uploadOk == 1 && $file_filename !== "" ) {
					$insdata  = new stdClass();
					$insdata->subject = $feedsubject;
					$insdata->message=$feedmessage;
					$insdata->user_id=$USER->id;
					$insdata->file_upload= $bannername;
					$insertbundle=$DB->insert_record('help_contact', $insdata);
					echo json_encode(['error' => 0 , 'message' => " Feedback submitted ", 'data'=> $insertbundle ]);
				}

		  } else {
		    echo json_encode(['error' => 1 , 'message' => "Sorry, there was an error uploading your file.", 'data'=> null ]);
		  }
		}
	}

	
	die;
?>