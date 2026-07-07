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
 * Certificates page renderer
 *
 * @package    theme_moove
 * @copyright  2020 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace theme_moove\output;

defined('MOODLE_INTERNAL') || die;

use renderable;
use templatable;
use renderer_base;
use stdClass;
use core_course_list_element;

/**
 * My certificates page renderer
 *
 * @package    theme_moove
 * @copyright  2020 Willian Mano - http://conecti.me
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class certificates implements renderable, templatable {
    /**
     * @var int $courseid The course id.
     */
    protected $courseid;

    /**
     * Certificates constructor.
     *
     * @param int $courseid
     */
    public function __construct($courseid = null) {
        $this->courseid = null;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output
     *
     * @return array
     *
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $USER, $CFG;

        $userCertificates = array();
        require_once($CFG->dirroot.'/course/renderer.php');
        $courses = enrol_get_users_courses($USER->id, true, '*', 'visible DESC, fullname ASC, sortorder ASC');
        if (empty($courses)) {
            return ['hascourse' => FALSE ];
        }
        foreach ($courses as  $course) {
            $certificateUserObj = new stdClass();
            $certificates = new \theme_moove\util\certificates($USER, $course->id);
            $issuedcertificates = $certificates->get_all_certificates();
            $instructor_details =   $this->certificate_instuctors_details($course->id);
            $pass_status =  $this->get_Course_Passed_Or_Not($course)->status;

            $certificateUserObj->coursename  = format_string($course->fullname, true, ['context' => \context_course::instance($course->id)]);
            $certificateUserObj->instructors = (count($instructor_details)) ? $instructor_details : false ;
            $certificateUserObj->hascertificates = (count($issuedcertificates)) ? true : false ;
            $certificateUserObj->coursescertificates = $issuedcertificates;
            $certificateUserObj->status = $pass_status;
            $certificateUserObj->statusint = $this->get_Course_Passed_Or_Not($course)->statusint;
            unset($certificates);
            array_push($userCertificates, $certificateUserObj);
        }
        return [ 'hascourse' => $userCertificates ];
    }

    public function certificate_instuctors_details($courseid = null){
        global $DB;
        $instructors = array();
        $show_users = [3, 4];
        if (!is_null($courseid)) {
            $coursecontext = \context_course::instance($courseid);
            if (!empty($coursecontext)) { 
                $sql =  "SELECT * FROM {role_assignments} WHERE contextid = $coursecontext->id AND roleid IN (".implode(',',$show_users).")";
                $role_assignments =  $DB->get_records_sql($sql, array());
                if (!empty($role_assignments)) {
                    foreach ($role_assignments as  $teachers) {
                        if ($DB->record_exists('user', array('id' => $teachers->userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1))) {
                            $ins_info =  $DB->get_record('user', array('id' => $teachers->userid, 'deleted' => 0, 'suspended' => 0, 'confirmed' => 1));
                            array_push($instructors, $ins_info);
                        }
                    }  
                }
            }
        }
        return $instructors;
    }

    public function get_Course_Passed_Or_Not($course = null){
        global $USER, $CFG;
        $status = new stdClass();
        
        $status->status = 'Failed';
        $status->statusint = 0;

        require_once($CFG->dirroot.'/course/renderer.php');
            $courseobj = new \core_course_list_element($course);
            $completion = new \completion_info($course);
            if ($completion->is_enabled()) {
                 if ($completion->is_course_complete($USER->id)) {
                    $status->status = 'Passed';
                    $status->statusint = 1;
                 }

            }
            $percentage = \core_completion\progress::get_course_progress_percentage($course, $USER->id);
                if (!is_null($percentage)) {
                    $percentage = floor($percentage);
                    $status->status = 'Course in progress';
                    $status->statusint = 2;  
                }
                if (is_null($percentage)) {
                    $status->status = 'Course Not Started';
                    $status->statusint = 0;
            }
       return $status;
    }
}