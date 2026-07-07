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
 * Custom moove extras functions
 *
 * @package    theme_moove
 * @copyright  2018 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moove\util;

use core_competency\api as competency_api;
use moodle_url;
use stdClass;
use DateTime;
use core_date;
use html_writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to get some extras info in Moodle.
 *
 * @package    theme_moove
 * @copyright  2019 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extras {
    /**
     * Returns all user enrolled courses with progress
     *
     * @param \stdClass $user
     *
     * @return array
     */
    public static function user_courses_with_progress($user) {
        global $USER, $CFG;

        if (($USER->id !== $user->id) && !is_siteadmin($USER->id)) {
            return [];
        }

        require_once($CFG->dirroot.'/course/renderer.php');

        $chelper = new \coursecat_helper();

        $courses = enrol_get_users_courses($user->id, true, '*', 'visible DESC, fullname ASC, sortorder ASC');

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
            }

            $course->link = $CFG->wwwroot."/course/view.php?id=".$course->id;

            // Summary.
            $course->summary = strip_tags($chelper->get_course_formatted_summary(
                $courseobj,
                array('overflowdiv' => false, 'noclean' => false, 'para' => false)
            ));

            $course->courseimage = self::get_course_summary_image($courseobj, $course->link);
        }

        return array_values($courses);
    }

    /**
     * Returns the first course's summary issue
     *
     * @param \core_course_list_element $course
     * @param string $courselink
     *
     * @return string
     */
    public static function get_course_summary_image($course, $courselink) {
        global $CFG;

        $contentimage = '';
        foreach ($course->get_course_overviewfiles() as $file) {
            $isimage = $file->is_valid_image();
            $url = file_encode_url("$CFG->wwwroot/pluginfile.php",
                '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                $file->get_filearea(). $file->get_filepath(). $file->get_filename(), !$isimage);
            if ($isimage) {
                $contentimage = \html_writer::link($courselink, \html_writer::empty_tag('img', array(
                    'src' => $url,
                    'alt' => $course->fullname,
                    'class' => 'card-img-top w-100')));
                break;
            }
        }

        if (empty($contentimage)) {
            $url = $CFG->wwwroot . "/theme/moove/pix/default_course.jpg";

            $contentimage = \html_writer::link($courselink, \html_writer::empty_tag('img', array(
                'src' => $url,
                'alt' => $course->fullname,
                'class' => 'card-img-top w-100')));
        }

        return $contentimage;
    }

    /**
     * Returns the user picture
     *
     * @param null $userobject
     * @param int $imgsize
     *
     * @return \moodle_url
     * @throws \coding_exception
     */
    public static function get_user_picture($userobject = null, $imgsize = 100) {
        global $USER, $PAGE;

        if (!$userobject) {
            $userobject = $USER;
        }

        $userimg = new \user_picture($userobject);

        $userimg->size = $imgsize;

        return $userimg->get_url($PAGE);
    }

    /**
     * Returns an array of all user competency plans
     *
     * @param \stdClass $user
     *
     * @return array
     *
     * @throws \coding_exception
     * @throws \required_capability_exception
     */
    public static function get_user_competency_plans($user) {
        global $USER;

        if (($USER->id !== $user->id) && !is_siteadmin($USER->id)) {
            return [];
        }

        $retorno = [];

        try {
            $plans = array_values(competency_api::list_user_plans($user->id));

            if (empty($plans)) {
                return [];
            }

            foreach ($plans as $plan) {
                $pclist = competency_api::list_plan_competencies($plan);

                $ucproperty = 'usercompetency';
                if ($plan->get('status') != 1) {
                    $ucproperty = 'usercompetencyplan';
                }

                $proficientcount = 0;
                foreach ($pclist as $pc) {
                    $usercomp = $pc->$ucproperty;

                    if ($usercomp->get('proficiency')) {
                        $proficientcount++;
                    }
                }

                $competencycount = count($pclist);
                $proficientcompetencypercentage = ((float) $proficientcount / (float) $competencycount) * 100.0;

                $progressclass = '';
                if ($proficientcompetencypercentage == 100) {
                    $progressclass = 'bg-success';
                }

                $retorno[] = [
                    'id' => $plan->get('id'),
                    'name' => $plan->get('name'),
                    'competencycount' => $competencycount,
                    'proficientcount' => $proficientcount,
                    'proficientcompetencypercentage' => $proficientcompetencypercentage,
                    'progressclass' => $progressclass
                ];
            }
        } catch (\Exception $e) {
            return [];
        }

        return $retorno;
    }

    /**
     * Returns the buttons displayed at the page header
     *
     * @param \context_course $context
     * @param \stdClass $user
     *
     * @return array
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function get_mypublic_headerbuttons($context, $user) {
        global $USER, $CFG;

        $headerbuttons = [];

        // Check to see if we should be displaying a message button.
        if (!empty($CFG->messaging) && $USER->id != $user->id && has_capability('moodle/site:sendmessage', $context)) {
            $iscontact = !empty(\core_message\api::get_contact($USER->id, $user->id)) ? 1 : 0;
            $contacttitle = $iscontact ? 'removecontact' : 'addcontact';
            $contacturlaction = $iscontact ? 'removecontact' : 'addcontact';
            $contactimage = $iscontact ? 'slicon-user-unfollow' : 'slicon-user-follow';
            $headerbuttons = [
                [
                    'title' => get_string('sendmessage', 'core_message'),
                    'url' => new \moodle_url('/message/index.php', array('id' => $user->id)),
                    'icon' => 'fa fa-comment-o',
                    'class' => 'btn btn-block btn-outline-primary'
                ],
                [
                    'title' => get_string($contacttitle, 'theme_moove'),
                    'url' => new \moodle_url('/message/index.php', [
                            'user1' => $USER->id,
                            'user2' => $user->id,
                            $contacturlaction => $user->id,
                            'sesskey' => sesskey()]
                    ),
                    'icon' => $contactimage,
                    'class' => 'btn btn-block btn-outline-dark ajax-contact-button',
                    'linkattributes' => \core_message\helper::togglecontact_link_params($user, $iscontact),
                ]
            ];

            \core_message\helper::togglecontact_requirejs();
        }

        return $headerbuttons;
    }
    /* get enrolled courses */

    public static function getSelfEnrolledCourses($userid = null){
        global $DB, $USER, $CFG;
        $enrolledcoursearray = array();
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
                                        $course->enrolltime = $enrolments->timestart;
                                        array_push($enrolledcoursearray, $course);
                                    }

                                }
                            }
                    }
                }
            }
        }
        return array_values($enrolledcoursearray);
    }

    public static function getSelfCoursesProgress($userid = null){
     global $DB, $USER, $CFG;
        $enrolledcoursearray = array();
        if (isloggedin()) {
            if ($DB->count_records('user_enrolments', array('userid' => $userid)) > 0) {
                $user_enrolments = $DB->get_records('user_enrolments', array('userid' => $userid));
                foreach ($user_enrolments as $enrolments) {
                    if ($DB->count_records('enrol', array('id' => $enrolments->enrolid)) > 0) {
                        $courses_enrolles = $DB->get_records('enrol', array('id' => $enrolments->enrolid));
                            foreach ($courses_enrolles as $courses) {
                                if($DB->count_records('course', array('id' => $courses->courseid)) > 0){
                                    $courses = $DB->get_records('course', array('id' => $courses->courseid));
                                    foreach ($courses as  $course) {
                                        $completion = new \completion_info($course);
                                            // First, let's make sure completion is enabled.
                                            if ($completion->is_enabled()) {
                                                $percentage = \core_completion\progress::get_course_progress_percentage($course, $userid);

                                                if (!is_null($percentage)) {
                                                    $percentage = floor($percentage)."%";
                                                }

                                                if (is_null($percentage)) {
                                                    $percentage = 'Not Started';
                                                }

                                                $course->progress  = $percentage;
                                            }else{
                                                $course->progress  = "Completion Not Enabled";
                                            }
                                        $course->time = 0;  
                                        $course->score  = 0;
                                        $course->enrolltime = $enrolments->timestart;
                                        array_push($enrolledcoursearray, $course);
                                    }

                                }
                            }
                    }
                }
            }
        }
        return array_values($enrolledcoursearray);   
    }

    public static function userSelfPrivatefiles(){
        global $CFG, $USER;
        $private_files = array();
        require_once("$CFG->dirroot/user/files_form.php");
        require_once("$CFG->dirroot/repository/lib.php");
        if (!isguestuser()) {
            $context = \context_user::instance($USER->id);
            require_capability('moodle/user:manageownfiles', $context);

            $maxbytes = $CFG->userquota;
            $maxareabytes = $CFG->userquota;
            if (has_capability('moodle/user:ignoreuserquota', $context)) {
                $maxbytes = USER_CAN_IGNORE_FILE_SIZE_LIMITS;
                $maxareabytes = FILE_AREA_MAX_BYTES_UNLIMITED;
            }
            $date = new DateTime("now", core_date::get_user_timezone_object());
            $fs = get_file_storage();
            $files = $fs->get_area_files($context->id,'user','private','*',$sort ="itemid,filepath,filename",$includedirs=true,$updatedsince=0); 
            if ($files != null) {
            $url = array();
                foreach($files as $file){
                    if (!is_null(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) && !empty(pathinfo($file->get_filename(), PATHINFO_EXTENSION))) {
                    $privatefile = new stdClass();
                    $privatefile->filename = $file->get_filename();
                    $privatefile->filesize = display_size($file->get_filesize());
                    $privatefile->filetype = $file->get_mimetype();
                    $privatefile->fileextension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                    $privatefile->content = $file->get_content();
                    // $privatefile->filecreated = new DateTime(date($file->get_timecreated(), core_date::get_user_timezone_object()));
                    $privatefile->filecreated = self::dateDiff(time(), $file->get_timecreated(), 2);
                    $privatefile->fileurl = new moodle_url("$CFG->wwwroot/pluginfile.php" . '/'. $file->get_contextid(). '/'. $file->get_component(). '/'.
                $file->get_filearea(). $file->get_filepath(). $file->get_filename(), ['forcedownload' => false]);
                    array_push($private_files, $privatefile);
                    }
                }
            }
        }
        return $private_files;
    }

    public static function overviewCourse($userid = null){
           global $DB, $USER, $CFG;
        $enrolledcourseselect = '';

        if (isloggedin()) {
            $userid = $USER->id;
            if ($DB->count_records('user_enrolments', array('userid' => $userid)) > 0) {
                $user_enrolments = $DB->get_records('user_enrolments', array('userid' => $userid));
                foreach ($user_enrolments as $enrolments) {
                    if ($DB->count_records('enrol', array('id' => $enrolments->enrolid)) > 0) {
                        $courses_enrolles = $DB->get_records('enrol', array('id' => $enrolments->enrolid));
                            foreach ($courses_enrolles as $courses) {
                                if($DB->count_records('course', array('id' => $courses->courseid)) > 0){
                                    $courses = $DB->get_records('course', array('id' => $courses->courseid, 'visible' => 1 ));
                                    $selectected = false;
                                    foreach ($courses as $key => $course) {
                                        if (!$selectected) {
                                        $enrolledcourseselect .=html_writer::tag('option', $course->fullname ,array('value' =>  $course->id, 'selected' => true));
                                        $selectected = true;
                                        }else{
                                            $enrolledcourseselect .=html_writer::tag('option', $course->fullname ,array('value' =>  $course->id));
                                        }
                                    }
                                }
                            }
                    }
                }
            }
        }
        return html_writer::tag('select', $enrolledcourseselect ,array('class'=>'form-control', 'id' =>'coursesoverview'));
    }

    public static function get_course_metadata($courseid) {
        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        // This is equivalent to the line above.
        //$handler = \core_course\customfield\course_handler::create();
        $datas = $handler->get_instance_data($courseid);
        $metadata = [];
        foreach ($datas as $data) {
            if (empty($data->get_value())) {
                continue;
            }
            $cat = $data->get_field()->get_category()->get('name');
            $metadata[$data->get_field()->get('shortname')] = $cat . ': ' . $data->get_value();
        }
        return $metadata;
    }

    public static function dateDiff($time1, $time2, $precision = 6) {
            if (!is_int($time1)) {
              $time1 = strtotime($time1);
            }
            if (!is_int($time2)) {
              $time2 = strtotime($time2);
            }
            if ($time1 > $time2) {
              $ttime = $time1;
              $time1 = $time2;
              $time2 = $ttime;
            }
            $intervals = array('year','month','day','hour','minute','second');
            $diffs = array();
            foreach ($intervals as $interval) {
              $ttime = strtotime('+1 ' . $interval, $time1);
              $add = 1;
              $looped = 0;
              while ($time2 >= $ttime) {
                $add++;
                $ttime = strtotime("+" . $add . " " . $interval, $time1);
                $looped++;
              }
              $time1 = strtotime("+" . $looped . " " . $interval, $time1);
              $diffs[$interval] = $looped;
            }
            $count = 0;
            $times = array();
            foreach ($diffs as $interval => $value) {
              if ($count >= $precision) {
                break;
              }
              if ($value > 0) {
                if ($value != 1) {
                  $interval .= "s";
                }
                $times[] = $value . " " . $interval;
                $count++;
              }
            }
        return implode(", ", $times);
    }

    public static function userlastlogins($userid = null){
        global $USER, $DB, $CFG;

        require_once($CFG->dirroot.'/course/lib.php'); 
        require_once($CFG->dirroot.'/report/usersessions/locallib.php');
        require_once($CFG->dirroot.'/report/log/locallib.php');
        require_once($CFG->libdir.'/adminlib.php');
        require_once($CFG->dirroot.'/lib/tablelib.php');
        if (is_null($userid)) {
           $userid = $USER->id;
        }
        $access = new stdClass();
        if ($USER->lastaccess) {
            $strlastaccess = format_time(time() - $USER->lastaccess);
            if ($strlastaccess != "now" ) {
            $strlastaccess = format_time(time() - $USER->lastaccess)." ago";
            }
        } else {
            $strlastaccess = get_string('never');
        }
        $access->lastaccess = $strlastaccess;
       
        $startweek = strtotime(self::getlastweek()->start_week);
        $endweek = strtotime(self::getlastweek()->end_week);
        
        $sql = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$userid.' AND action = "loggedin" AND timecreated BETWEEN '.$startweek.' AND '.$endweek;
        $previousweek=$DB->get_records_sql($sql);
        $access->lastweekaccess = count($previousweek);


        $firstdaymonth = strtotime(self::getlastmonth()->firstday);
        $lastdaymonth = strtotime(self::getlastmonth()->lastday);
        
        $sqlmonth = 'SELECT id , timecreated FROM {logstore_standard_log} WHERE target = "user" AND userid = '.$userid.' AND action = "loggedin" AND timecreated BETWEEN '.$firstdaymonth.' AND '.$lastdaymonth;
        $previousmonth=$DB->get_records_sql($sqlmonth);
        $access->lastmonthaccess = count($previousmonth);

        return $access;
    }

    public function report_usersessions_format_duration($duration) {

        // NOTE: The session duration is not accurate thanks to
        //       $CFG->session_update_timemodified_frequency setting.
        //       Also there is no point in showing days here because
        //       the session cleanup should purge all stale sessions
        //       regularly.

        if ($duration < 60) {
            return get_string('now');
        }

        if ($duration < 60 * 60 * 2) {
            $minutes = (int)($duration / 60);
            $ago = $minutes . ' ' . get_string('minutes');
            return get_string('ago', 'core_message', $ago);
        }

        $hours = (int)($duration / (60 * 60));
        $ago = $hours . ' ' . get_string('hours');
        return get_string('ago', 'core_message', $ago);
    }

    public static function getlastweek(){
        $returweek = new stdClass();
        $previous_week = strtotime("-1 week +1 day");
        $start_week = strtotime("last sunday midnight",$previous_week);
        $end_week = strtotime("next saturday",$start_week);
        $start_week = date("Y-m-d",$start_week);
        $end_week = date("Y-m-d",$end_week);
        $returweek->start_week = $start_week;
        $returweek->end_week = $end_week;
        return $returweek;
    }

    public static function getlastmonth($format = "Y-n-j"){
        $returnlastmonth = new stdClass();
        $returnlastmonth->firstday = date($format, strtotime("first day of previous month"));
        $returnlastmonth->lastday = date($format, strtotime("last day of previous month"));
        return $returnlastmonth;
    }

    public static function getyesterday($format = "Y-n-j"){
      return  date($format,strtotime("-1 days"));
    }

    public static function allbadges(){
        global $DB, $USER, $CFG, $PAGE;

        require_once($CFG->libdir . '/badgeslib.php');
        require_once($CFG->libdir . '/filelib.php');

        $page        = optional_param('page', 0, PARAM_INT);
        $search      = optional_param('search', '', PARAM_CLEAN);
        $clearsearch = optional_param('clearsearch', '', PARAM_TEXT);
        $download    = optional_param('download', 0, PARAM_INT);
        $hash        = optional_param('hash', '', PARAM_ALPHANUM);
        $downloadall = optional_param('downloadall', false, PARAM_BOOL);
        $hide        = optional_param('hide', 0, PARAM_INT);
        $show        = optional_param('show', 0, PARAM_INT);

        require_login();

        if (empty($CFG->enablebadges)) {
            return get_string('badgesdisabled', 'badges');
        }

        if (isguestuser()) {
            die();
        }

        if ($page < 0) {
            $page = 0;
        }

        if ($clearsearch) {
            $search = '';
        }

        if ($hide) {
            require_sesskey();
            $DB->set_field('badge_issued', 'visible', 0, array('id' => $hide, 'userid' => $USER->id));
        } else if ($show) {
            require_sesskey();
            $DB->set_field('badge_issued', 'visible', 1, array('id' => $show, 'userid' => $USER->id));
        } else if ($download && $hash) {
            require_sesskey();
            $badge = new badge($download);
            $name = str_replace(' ', '_', $badge->name) . '.png';
            $name = clean_param($name, PARAM_FILE);
            $filehash = badges_bake($hash, $download, $USER->id, true);
            $fs = get_file_storage();
            $file = $fs->get_file_by_hash($filehash);
            send_stored_file($file, 0, 0, true, array('filename' => $name));
        } else if ($downloadall) {
            require_sesskey();
            badges_download($USER->id);
        }

        $context = \context_user::instance($USER->id);
        require_capability('moodle/badges:manageownbadges', $context);

        // Include JS files for backpack support.

        $output = $PAGE->get_renderer('core', 'badges');
        $badges = badges_get_user_badges($USER->id);

        // echo $OUTPUT->header();
        $success = optional_param('success', '', PARAM_ALPHA);
        $warning = optional_param('warning', '', PARAM_ALPHA);
        if (!empty($success)) {
            echo $OUTPUT->notification(get_string($success, 'core_badges'), 'notifysuccess');
        } else if (!empty($warning)) {
            echo $OUTPUT->notification(get_string($warning, 'core_badges'), 'warning');
        }
        $totalcount = count($badges);
        $records = badges_get_user_badges($USER->id, null, $page, BADGE_PERPAGE, $search);

        $userbadges             = new \core_badges\output\badge_user_collection($records, $USER->id);
        $userbadges->sort       = 'dateissued';
        $userbadges->dir        = 'DESC';
        $userbadges->page       = $page;
        $userbadges->perpage    = BADGE_PERPAGE;
        $userbadges->totalcount = $totalcount;
        $userbadges->search     = $search;

        return $output->render($userbadges);
    }



}
