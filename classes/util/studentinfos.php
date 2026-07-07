<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Custom moove student infos
 *
 * @package    theme_moove
 * @copyright  2020 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moove\util;

defined('MOODLE_INTERNAL') || die();

use core_course_list_element;
use html_writer;
use moodle_url;
use dedication_manager;
use dedication_utils;
use grade_item;
use chart_bar;
use stdClass;
/**
 * Class to get some student infos in Moodle.
 *
 * @package    theme_moove
 * @copyright  2020 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class studentinfos {
    /**
     * Returns the courses in progess.
     *
     * @return int
     * @throws \dml_exception
     */
    public function get_totalcourses($user) {
        global $USER, $CFG;

        if (($USER->id !== $user->id) && !is_siteadmin($USER->id)) {
            return [];
        }
        if (isloggedin()) {
        	$user = $USER;
        }

        require_once($CFG->dirroot.'/course/renderer.php');

        $chelper = new \coursecat_helper();

        $courses = enrol_get_users_courses($user->id, true, '*', 'visible DESC, fullname ASC, sortorder ASC');

        $progresscourses = array();

        foreach ($courses as $course) {
            $course->fullname = strip_tags($chelper->get_course_formatted_name($course));

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
                $course->completed = $completion->is_course_complete($user->id);
                $course->progress  = $percentage;
                if ($percentage) {
                	array_push($progresscourses, $course);
                }
            }
        }
       return count($progresscourses);
    }

    /**
     * Returns the completed course.
     *
     * @return int
     * @throws \dml_exception
     */

    public function get_completed_courses($user){
         global $USER, $CFG;

        if (($USER->id !== $user->id) && !is_siteadmin($USER->id)) {
            return [];
        }
        if (isloggedin()) {
        	$user = $USER;
        }

        require_once($CFG->dirroot.'/course/renderer.php');

        $chelper = new \coursecat_helper();

        $courses = enrol_get_users_courses($user->id, true, '*', 'visible DESC, fullname ASC, sortorder ASC');

        $completedcourses = array();

        foreach ($courses as $course) {
            $course->fullname = strip_tags($chelper->get_course_formatted_name($course));

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
                $course->completed = $completion->is_course_complete($user->id);
                $course->progress  = $percentage;
                if ($completion->is_course_complete($user->id)) {
                	array_push($completedcourses, $course);
                }
            }
        }
       return count($completedcourses);
    }

    /**
     * Returns the total training time .
     *
     * @return int
     * @throws \dml_exception
     */
    public function get_total_training_time($courseid = array()){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/theme/moove/classes/util/dedication_lib.php');
        require_once($CFG->dirroot.'/course/renderer.php');
        if (isloggedin()) {
            $userid = $USER->id;
        }

        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        if (!empty($courses)) {
            $totaldedication = 0;
            // echo "<pre>";
            foreach ($courses as  $value) {
                $courseid = $value->id;
                if (!is_null($courseid)) {
                    $course = $DB->get_record("course", array("id" => $courseid), '*', MUST_EXIST);
                    
                    $mintime = optional_param('mintime', $course->startdate, PARAM_INT);
                    $maxtime = optional_param('maxtime', time(), PARAM_INT);
                    $limit = optional_param('limit', BLOCK_DEDICATION_DEFAULT_SESSION_LIMIT, PARAM_INT);


                    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                    if (!is_enrolled(\context_course::instance($course->id), $user)) {
                        print_error('usernotincourse');
                    }

                    $dm = new dedication_manager($course, $mintime, $maxtime, $limit);
                    
                    $rows = $dm->get_user_dedication($user);

                    foreach ($rows as $index => $row) {
                        $totaldedication += $row->dedicationtime;
                        $rows[$index] = array(
                            userdate($row->start_date),
                            dedication_utils::format_dedication($row->dedicationtime),
                            dedication_utils::format_ips($row->ips),
                        );
                    }
                    // print_r($rows);
             } 
            }
            // die;
            return dedication_utils::format_dedication($totaldedication);
        }
        else
        {
         return 0;
        }
    }

    /**
     * Returns the counts of badges  .
     *
     * @return int
     * @throws \dml_exception
     */

    public function get_badeges_counts(){
        global $DB, $USER;
        $badgescounts = 0;
        if (isloggedin()) {
            $userid = $USER->id;
            $badgescounts = $DB->count_records('badge_issued', array('userid' => $USER->id));
        }
        return $badgescounts;
    }

    /**
     * Returns the points earned.
     *
     * @return int
     * @throws \dml_exception
     */
    public function get_points_earned(){
        global $DB, $USER, $CFG;
        $ponitsearned = 0;
        require_once($CFG->dirroot.'/grade/lib.php');
        require_once($CFG->dirroot.'/grade/querylib.php');
        if (isloggedin()) {
            $userid = $USER->id;
        }
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        if (!empty($courses)) {
            foreach ($courses as $course) {       
                $resultkrb = grade_get_course_grades($course->id, $userid);
                    if (!is_null($resultkrb)) {
            		$grd = $resultkrb->grades[$USER->id]; 
            		if ($grd->grade) {
                        $ponitsearned += $grd->grade; 
                    }
                }
            }
        }
        return $ponitsearned;
    }

    public function grid_view_course($userid){
        global $DB, $USER, $CFG;
    	require_once($CFG->dirroot.'/course/renderer.php');
        $gridcourses = "";
        if (isloggedin()) {
            if ($DB->count_records('user_enrolments', array('userid' => $userid)) > 0) {
                $user_enrolments = $DB->get_records('user_enrolments', array('userid' => $userid));
                foreach ($user_enrolments as $enrolments) {
                    if ($DB->count_records('enrol', array('id' => $enrolments->enrolid)) > 0) {
                        $courses_enrolles = $DB->get_records('enrol', array('id' => $enrolments->enrolid));
                            foreach ($courses_enrolles as $courses) {
                                if($DB->count_records('course', array('id' => $courses->courseid)) > 0){
                                    $courses = $DB->get_records('course', array('id' => $courses->courseid, 'visible' => 1));
                                    foreach ($courses as  $course) {

                                    	$completion = new \completion_info($course);
								            // First, let's make sure completion is enabled.
								            if ($completion->is_enabled()) {
								                $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);

								                if (!is_null($percentage)) {
								                    $percentage = floor($percentage);
								                }

								                if (is_null($percentage)) {
								                    $percentage = 0;
								                }
								                // Add completion data in course object.
								                // $completion->is_course_complete($userid);
								                $course->progress  = $percentage;
								            }


                                        if (self::course_image($course->id) != "0" && !empty(self::course_image($course->id))) {
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'col-md-4 my-3'));
                                           $gridcourses .= html_writer::start_tag('a', array( 'href' => new moodle_url('/course/view.php', array('id'=> $course->id ))));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'coursesection'));
                                           $gridcourses .= self::course_image($course->id);
                                           $gridcourses .= html_writer::tag('h6',$course->fullname,array('class' => 'h6 my-2 mx-1 p-2 text-primary text-center'));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'my-2 p-2'));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'progress border border-success rounded'));
                                           $gridcourses .= html_writer::tag('div', $percentage."%", array('class' => 'progress-bar bg-success rounded', 'role'=>"progressbar", 'style'=>"width: $percentage%" ,'aria-valuenow'=>"$percentage", 'aria-valuemin'=>"0", 'aria-valuemax'=>"100"));
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('a');
                                           $gridcourses .= html_writer::end_tag('div');
                                        }
                                    }

                                }
                            }
                    }
                }
            }
        }
        return html_writer::tag('div',$gridcourses, array('class' => 'row'));
    }

        public function list_view_courses($userid){
        global $DB, $USER, $CFG;
        $gridcourses = "";

        if (isloggedin()) {
            if ($DB->count_records('user_enrolments', array('userid' => $userid)) > 0) {
                $user_enrolments = $DB->get_records('user_enrolments', array('userid' => $userid));
                foreach ($user_enrolments as $enrolments) {
                    if ($DB->count_records('enrol', array('id' => $enrolments->enrolid)) > 0) {
                        $courses_enrolles = $DB->get_records('enrol', array('id' => $enrolments->enrolid));
                            foreach ($courses_enrolles as $courses) {
                                if($DB->count_records('course', array('id' => $courses->courseid)) > 0){
                                    $courses = $DB->get_records('course', array('id' => $courses->courseid, 'visible' => 1));
                                    foreach ($courses as  $course) {

                                        $completion = new \completion_info($course);
                                            // First, let's make sure completion is enabled.
                                            if ($completion->is_enabled()) {
                                                $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);

                                                if (!is_null($percentage)) {
                                                    $percentage = floor($percentage);
                                                }

                                                if (is_null($percentage)) {
                                                    $percentage = 0;
                                                }
                                                // Add completion data in course object.
                                                // $completion->is_course_complete($userid);
                                                $course->progress  = $percentage;
                                            }
                                        if (self::course_image($course->id) != "0" && !empty(self::course_image($course->id))) {

                                           $gridcourses .= html_writer::start_tag('li', array('class' => 'my-3'));
                                           $gridcourses .= html_writer::start_tag('a', array( 'href' => new moodle_url('/course/view.php', array('id'=> $course->id ))));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'coursesection'));
                                           $gridcourses .= html_writer::tag('h6',$course->fullname,array('class' => 'h6 my-2 mx-1 p-2 text-primary text-center'));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'my-2 p-2'));
                                           $gridcourses .= html_writer::start_tag('div', array('class' => 'progress border border-success rounded'));
                                           $gridcourses .= html_writer::tag('div', $percentage.'%', array('class' => 'progress-bar bg-success rounded', 'role'=>"progressbar", 'style'=>"width: $percentage%;" ,'aria-valuenow'=>"$percentage", 'aria-valuemin'=>"0", 'aria-valuemax'=>"100"));
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('div');
                                           $gridcourses .= html_writer::end_tag('a');
                                           $gridcourses .= html_writer::end_tag('li');
                                        }
                                    }

                                }
                            }
                    }
                }
            }
        }
        return html_writer::tag('ul',$gridcourses, array('class' => 'listcourse'));
    }



    public function course_image($courseid) {
        global $DB, $CFG;
        $courserecord = $DB->get_record('course', array('id' => $courseid));
        $course = new core_course_list_element($courserecord);
        foreach ($course->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $file->get_contextid() . '/' . $file->get_component() . '/' .
                $file->get_filearea() . $file->get_filepath() . $file->get_filename(), !$isimage);
            if ($isimage) {
                return html_writer::start_tag('div', array('class' => 'courseimage' ,  'style'=>"background-image: url('$url');" )).html_writer::end_tag('div');
            } else {
                return html_writer::start_tag('div', array('class' => 'courseimage' ,  'style'=>"background-color: green" )).html_writer::end_tag('div');
            }
        }
    }

    public function enrolledincourse(){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/theme/moove/classes/util/dedication_lib.php');
        require_once($CFG->dirroot.'/course/renderer.php');
        if (isloggedin()) {
            $userid = $USER->id;
        }
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        return count($courses);
    }

    public function totalcertificatetouser(){
        global $DB, $USER, $CFG;
        $totat_cert = 0;
        if (isloggedin()) {
            $userid = $USER->id;
        }
        if (!is_null($userid)) {
           $totat_cert = $DB->count_records('customcert_issues', array('userid' => $userid));
        }
        return $totat_cert;
    }
    public function studentlevel(){
        return 0;
    }

    public function trainingchartpercourse(){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/theme/moove/classes/util/dedication_lib.php');
        require_once($CFG->dirroot.'/course/renderer.php');
        if (isloggedin()) {
            $userid = $USER->id;
        }
        $chartdata = new stdClass();
        $coursenamearray = array();
        $userdedicationtime = array();
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        if (!empty($courses)) {
            foreach ($courses as  $value) {
                array_push($coursenamearray, $value->fullname);
                $courseid = $value->id;
                if (!is_null($courseid)) {
                    $course = $DB->get_record("course", array("id" => $courseid), '*', MUST_EXIST);
                    $mintime = optional_param('mintime', $course->startdate, PARAM_INT);
                    $maxtime = optional_param('maxtime', time(), PARAM_INT);
                    $limit = optional_param('limit', BLOCK_DEDICATION_DEFAULT_SESSION_LIMIT, PARAM_INT);
                    $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);
                    $dm = new dedication_manager($course, $mintime, $maxtime, $limit);
                    $rows = $dm->get_user_dedication($user);
                    $totaldedication = 0;
                    foreach ($rows as $index => $row) {
                        $totaldedication += $row->dedicationtime;
                        $rows[$index] = array(
                            userdate($row->start_date),
                            dedication_utils::format_dedication($row->dedicationtime),
                            dedication_utils::format_ips($row->ips),
                        );
                    }
                    $totaldedication = abs($totaldedication)/3600;
                    array_push($userdedicationtime, round($totaldedication, 2));
             }

            }
            $chartdata->label=$coursenamearray;
            $chartdata->data=$userdedicationtime;
            // print_r($chartdata); die;
            return json_encode($chartdata);
        }
        else
        {
         return FALSE;
        }

    }

    public function getcoursecompletiondata(){
        global $DB, $USER, $CFG;
        require_once($CFG->dirroot.'/theme/moove/classes/util/dedication_lib.php');
        require_once($CFG->dirroot.'/course/renderer.php');
        if (isloggedin()) {
            $userid = $USER->id;
        }
        $chartdata = new stdClass();
        $courses = enrol_get_users_courses($userid, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        if (empty($courses)) {
            return FALSE;
        }else{
        $completedcourses = array();
        $courseonprogresscourses = array();
        $coursenotstarted = array();

        $totalcompletedcourse = 0;
        $totalcoursesinprogress = 0;
        $totalcoursenotstarted = 0;
        $totalfailedcourse = 0;

        foreach ($courses as $course) {
            $courseobj = new \core_course_list_element($course);
            $completion = new \completion_info($course);
            // First, let's make sure completion is enabled.
            if ($completion->is_enabled()) {
                $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);
                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                    array_push($courseonprogresscourses, $course);
                }
                if (is_null($percentage)) {
                    $percentage = 0;
                    array_push($coursenotstarted, $course);
                }
                // Add completion data in course object.
                $course->completed = $completion->is_course_complete($userid);
                $course->progress  = $percentage;
                if ($completion->is_course_complete($userid)) {
                    array_push($completedcourses, $course);
                }
            }
        }
        $totalcourses = count($courses);
        $totalcompletedcourse = count($completedcourses);
        $totalcoursenotstarted = count($coursenotstarted);
        $totalcoursesinprogress = count($courseonprogresscourses);

        $percentage_completed_course =  round( ($totalcompletedcourse / $totalcourses) * 100 , 2);
        $percentage_progress_course = round(($totalcoursesinprogress / $totalcourses) * 100 , 2);
        $percentage_notstarted_course =  round(($totalcoursenotstarted / $totalcourses) * 100, 2);
        $percentage_failed_course =  round(100 - ($percentage_completed_course + $percentage_progress_course + $percentage_notstarted_course), 2);

        $dataarray = array($percentage_completed_course, $percentage_notstarted_course, $percentage_progress_course, $percentage_failed_course );
        $labels = array('completed', 'Not started', 'In progress', 'Failed');
        $color =  ['rgb(128, 183, 235)','rgb(197, 215, 232)','rgb(164, 200, 234)','rgb(209, 226, 243)'];

        $chartdatapie = new stdClass();
        $chartdatapie->label= $labels;
        $chartdatapie->data= $dataarray;
        $chartdatapie->backgroundcolor = $color;

        $returndata = new stdClass();
        $returndata->chartdata = json_encode($chartdatapie);
        $returndata->totalcourses = $totalcourses;
        $returndata->totalcompletedcourse = $totalcompletedcourse;
            return $returndata; 
        }

    }

    /* PENDING */

    public function testResultChart(){
        global $DB, $USER, $CFG;
        if (isloggedin()) {
            $userid = $USER->id;
        }
        $chartdata = new stdClass();
        $chartdata->passpercentage = 0;
        $chartdata->attempt = 0;
        $chartdata->averagescore = 0;
        $totalQuiz = $DB->get_records('quiz_attempts', array('userid' => $userid ));
        if (empty($totalQuiz)) {
            return $chartdata;
        }else{
            $totalattemtQuiz = 0;
            foreach ($totalQuiz as $quizes) {
                $totalattemtQuiz += $quizes->attempt;
            }
            $chartdata->attempt = $totalattemtQuiz;
        }

    }    

}