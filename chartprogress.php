<?php
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
global $USER, $CFG, $DB,$OUTPUT; 
require_once($CFG->dirroot.'/course/renderer.php');
require_once($CFG->dirroot.'/lib/completionlib.php');
require_once($CFG->dirroot.'/lib/enrollib.php');
require_login();
/**
 * chart api  
 * progress
 * user activity
 */
$courseid = null;
$user = null;
$returndata = array();

    $returnapidata=new stdClass();
    $returnapidata->coursepercentage = 0;


    if (isset($_POST['courseid']) && !empty($_POST['courseid'])) {
        $courseid = $_POST['courseid'];
    }
    if(isloggedin()){
        $user = $USER;
    }
    if (!is_null($courseid)) {
        $totalpercentage = 0;
        $averagepercentage = 0;
        $totalusers = 0;

        $allusersenrolledincourses = get_enrolled_users(context_course::instance($courseid));
        if (!empty($allusersenrolledincourses)) {
            $totalusers = count($allusersenrolledincourses);                
           foreach ($allusersenrolledincourses as $enrolleduser) { 
                /* get completion percentage for all users */
            $course = $DB->get_record('course', array('id' => $courseid));
            $completion = new \completion_info($course);
            if ($completion->is_enabled()) {
                $percentage = \core_completion\progress::get_course_progress_percentage($course, $enrolleduser->id);
                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                }
                if (is_null($percentage)) {
                    $percentage = 0;
                }
                // Add completion data in course object.
                if ($completion->is_course_complete($enrolleduser->id)) {
                    $percentage = 100;
                }
            }
            $totalpercentage += $percentage;
           }
           $averagepercentage = $totalpercentage/$totalusers;
        }

    $returnapidata->averagepercentage = $averagepercentage;

        $course = $DB->get_record('course', array('id' => $courseid));
            $courseobj = new \core_course_list_element($course);
            $completion = new \completion_info($course);
            // First, let's make sure completion is enabled.
            if ($completion->is_enabled()) {
                $percentage = \core_completion\progress::get_course_progress_percentage($course, $user->id);
                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                }
                if (is_null($percentage)) {
                    $percentage = 0;
                }
                // Add completion data in course object.
                if ($completion->is_course_complete($user->id)) {
                	$percentage = 100;
                }
            }
    $returnapidata->coursepercentage = $percentage;        
    }

    
        $badge_issued_sql = 'SELECT id FROM {badge_issued} WHERE  userid = '.$user->id;
        $badges=$DB->get_records_sql($badge_issued_sql);
        if (empty($badges)) {
            $returnapidata->badge_issued_count = 0;        
        }else{
           $returnapidata->badge_issued_count = count($badges);
        }

    /* loging */
        
        $previous_week = strtotime("-1 week +1 day");
        $start_week = strtotime("last sunday midnight",$previous_week);
        $end_week = strtotime("next saturday",$start_week);
        $firstday = strtotime("first day of previous month");
        $lastday = strtotime("last day of previous month");
        $yesterday = strtotime("-1 days");
        $beginOfDay = strtotime("today");
        $endOfDay   = strtotime("tomorrow", $beginOfDay) - 1;
        $startofyear = strtotime('first day of january this year');
        $endofyear = strtotime('last day of december this year');
        $yesterday = strtotime('-1 day', $beginOfDay);

        $yesterdaysql = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$user->id.' AND action = "loggedin" AND timecreated BETWEEN '.$yesterday.' AND '.$beginOfDay;
        $yesterdays=$DB->get_records_sql($yesterdaysql);
        if (!empty($yesterdays)) {
                $yesterdaysdata = array();
                foreach ($yesterdays as $yester) {
                    $yester->timecreated = date('d/m', $yester->timecreated);
                    array_push($yesterdaysdata, $yester->timecreated);
                }
                $yesterdaychartdatas = array_count_values($yesterdaysdata);
                $yesterdaychartdatas_labels = array();
                $yesterdaychartdatas_logincounts = array();
                foreach ($yesterdaychartdatas as $key => $value) {
                    array_push($yesterdaychartdatas_labels, $key); 
                    array_push($yesterdaychartdatas_logincounts, $value);
                }

                $returnapidata_yesterday=new stdClass();
                $returnapidata_yesterday->labels = $yesterdaychartdatas_labels;
                $returnapidata_yesterday->data = $yesterdaychartdatas_logincounts;
                $returnapidata->yesterdaychart = $returnapidata_yesterday;        
        }else{
            $returnapidata->yesterdaychart = null;
        }



         $sql = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$user->id.' AND action = "loggedin" AND timecreated BETWEEN '.$start_week.' AND '.$end_week;
        $previousweeks=$DB->get_records_sql($sql);
        if (!empty($previousweeks)) {
                $weekdata = array();
                foreach ($previousweeks as $previousweek) {
                    $previousweek->timecreated = date('d/m', $previousweek->timecreated);
                    array_push($weekdata, $previousweek->timecreated);
                }
                $weekchartdatas = array_count_values($weekdata);
                $weekchartdatas_labels = array();
                $weekchartdatas_logincounts = array();
                foreach ($weekchartdatas as $key => $value) {
                    array_push($weekchartdatas_labels, $key); 
                    array_push($weekchartdatas_logincounts, $value);
                }

                $returnapidata_week=new stdClass();
                $returnapidata_week->labels = $weekchartdatas_labels;
                $returnapidata_week->data = $weekchartdatas_logincounts;
                $returnapidata->weekchart = $returnapidata_week;        
        }else{
            $returnapidata->weekchart = null;
        }

        $sqlmonth = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$user->id.' AND action = "loggedin" AND timecreated BETWEEN '.$firstday.' AND '.$lastday;
        $previousmonths=$DB->get_records_sql($sqlmonth);
        
        if (!empty($previousmonths)) {
                $monthdata = array();
                foreach ($previousmonths as $previousmonth) {
                    $previousmonth->timecreated = date('d/m', $previousmonth->timecreated);
                    array_push($monthdata, $previousmonth->timecreated);
                }
                $monthchartdatas = array_count_values($monthdata);
                $monthchartdatas_labels = array();
                $monthchartdatas_logincounts = array();
                foreach ($monthchartdatas as $key => $value) {
                    array_push($monthchartdatas_labels, $key); 
                    array_push($monthchartdatas_logincounts, $value);
                }

                $returnapidata_month=new stdClass();
                $returnapidata_month->labels = $monthchartdatas_labels;
                $returnapidata_month->data = $weekchartdatas_logincounts;
                $returnapidata->monthchart = $returnapidata_month;
        }else{
            $returnapidata->monthchart = null;
        }

        $currentday = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$user->id.' AND action = "loggedin" AND timecreated BETWEEN '.$beginOfDay.' AND '.$endOfDay;
        $currentdays=$DB->get_records_sql($currentday);

        if (!empty($currentdays)) {
                $todaydata = array();
                foreach ($currentdays as $currentday) {
                    $currentday->timecreated = date('H:i:s', $currentday->timecreated);
                    array_push($todaydata, $currentday->timecreated);
                }
                $currentdays = array_count_values($todaydata);
                $currentdays_labels = array();
                $currentdays_logincounts = array();
                foreach ($currentdays as $key => $value) {
                    array_push($currentdays_labels, $key); 
                    array_push($currentdays_logincounts, $value);
                }

                $returnapidata_today=new stdClass();
                $returnapidata_today->labels = $currentdays_labels;
                $returnapidata_today->data = $currentdays_logincounts;
                $returnapidata->todaychart = $returnapidata_today;            
        }else{
            $returnapidata->todaychart = null;
        }

        $yearsd = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$user->id.' AND action = "loggedin" AND timecreated BETWEEN '.$startofyear.' AND '.$endofyear;
        $yearsdata=$DB->get_records_sql($yearsd);
        if (!empty($yearsdata)) {
                $yeardatare = array();
                foreach ($yearsdata as $yeardata) {
                    $yeardata->timecreated = date('M Y', $yeardata->timecreated);
                    array_push($yeardatare, $yeardata->timecreated);
                }
                $currentyears = array_count_values($yeardatare);
                $currentyears_labels = array();
                $currentyears_logincounts = array();
                foreach ($currentyears as $key => $value) {
                    array_push($currentyears_labels, $key); 
                    array_push($currentyears_logincounts, $value);
                }

                $returnapidata_year=new stdClass();
                $returnapidata_year->labels = $currentyears_labels;
                $returnapidata_year->data = $currentyears_logincounts;
                $returnapidata->yearchart = $returnapidata_year;           
        }else{
            $returnapidata->yearchart = null;
        }

echo json_encode($returnapidata);

die;
    