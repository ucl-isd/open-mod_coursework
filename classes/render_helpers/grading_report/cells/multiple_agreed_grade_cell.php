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
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\render_helpers\grading_report\cells;

use html_writer;
use mod_coursework\ability;
use mod_coursework\grade_judge;
use mod_coursework\grading_table_row_base;
use mod_coursework\models\feedback;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;
use pix_icon;

/**
 * Class feedback_cell
 */
class multiple_agreed_grade_cell extends cell_base {

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * @param array $items
     */
    protected function after_initialisation($items) {
        $this->stage = $items['stage'];
    }

    /**
     * @param grading_table_row_base $rowobject
     * @return string
     */
    public function get_table_cell($rowobject) {        
        $content = $this->get_content($rowobject);
        return $this->get_new_cell_with_class($content);
    }

    /**
     * @param $rowobject
     * @return \html_table_cell|string
     */
    public function get_content($rowobject) {

        global $USER, $OUTPUT;

        //if coursework uses sampling check if any enabled for this submission, otherwise there is no agreed grade
        if ($rowobject->get_coursework()->sampling_enabled() && $rowobject->get_submission() && !$rowobject->get_submission()->sampled_feedback_exists()) {
            $content = get_string('singlemarker', 'coursework');
            return $content;
        }
        $ability = new ability(user::find($USER, false), $rowobject->get_coursework());

        $content = '';

        $finalfeedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());

        if ($finalfeedback !== false) {
            $grade_judge = new grade_judge($this->coursework);
            $content .= $grade_judge->grade_to_display($finalfeedback->get_grade());
          //  $content .= html_writer::empty_tag('br');
         //   $content .= ' by: ' . $finalfeedback->get_assesor_username();
        }

        // Edit/new link
        $existing_feedback = $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable());
        $title = get_string('editfinalgrade', 'coursework');
        $icon = new pix_icon('edit', $title, 'coursework');
        $iconlink = '';

        if ($existing_feedback && $ability->can('edit', $existing_feedback)) {

            $feedback_route_params = array(
                'feedback' => $finalfeedback
            );
            $link = $this->get_router()->get_path('ajax edit feedback', $feedback_route_params);

            $iconlink = $OUTPUT->action_icon($link,
                                             $icon,
                                             null,
                                             array(
                                                 'class' => 'edit_final_feedback',
                                                 'id' => 'edit_final_feedback_' . $rowobject->get_coursework()
                                                     ->get_allocatable_identifier_hash($rowobject->get_allocatable())));

        } else if ($rowobject->has_submission()) { // New

            $feedback_params = array(
                'submissionid' => $rowobject->get_submission()->id,
                'assessorid' => $USER->id,
                'stage_identifier' => $this->stage->identifier(),
            );
            $new_feedback = feedback::build($feedback_params);

            //if the user is a site admin then they can add final feedback
            if ($ability->can('new', $new_feedback) || is_siteadmin()) {
                $title = get_string('addfinalfeedback', 'coursework');
                $feedback_route_params = array(
                    'submission' => $rowobject->get_submission(),
                    'assessor' => $USER,
                    'stage' => $this->stage,
                );
                $link = $this->get_router()->get_path('ajax new final feedback', $feedback_route_params);

                $iconlink = $OUTPUT->action_link($link,
                                                 $title,
                                                 null,
                                                 array('class' => 'new_final_feedback',
                                                       'id' => 'new_final_feedback_' . $rowobject->get_coursework()
                                                        ->get_allocatable_identifier_hash($rowobject->get_allocatable())));

            } else if ($existing_feedback && $ability->can('show', $existing_feedback)) {

                $linktitle = get_string('viewfeedback', 'mod_coursework');
                $link_id = "show_feedback_" . $rowobject->get_coursework()
                        ->get_allocatable_identifier_hash($rowobject->get_allocatable());
                $link = $this->get_router()
                    ->get_path('show feedback', array('feedback' => $this->stage->get_feedback_for_allocatable($rowobject->get_allocatable())));
                $iconlink = $OUTPUT->action_link($link,
                                                 $linktitle,
                                                 null,
                                                 array('class' => 'show_feedback', 'id' => $link_id));
            }
        }

        if ($iconlink) {
            $content .= ' ' . $iconlink;
        }

        if ($finalfeedback !== false) {
            $content .= html_writer::empty_tag('br');
             if ((!$this->coursework->sampling_enabled() || $rowobject->get_submission()->sampled_feedback_exists()) && ($finalfeedback->get_feedbacks_assessorid() == 0
                 && $finalfeedback->timecreated == $finalfeedback->timemodified)
                 || $finalfeedback->lasteditedbyuser == 0) { // if the grade was automatically agreed
                 $content .= "(".get_string('automaticagreement', 'coursework').")";
             } else {
                 $content .= ' by: ' . $finalfeedback->get_assesor_username();
             }
        }
        
        return $content;
    }

    /**
     * @param array $options
     * @return string
     */
    public function get_table_header($options = []) {

        //adding this line so that the sortable heading function will make a sortable link unique to the table
        //if tablename is set
        $tablename = (isset($options['tablename'])) ? $options['tablename'] : '';

        $column_name = get_string('agreedgrade', 'coursework');
        return $this->helper_sortable_heading($column_name, 'finalgrade', $options['sorthow'], $options['sortby'], $tablename);
    }

    /**
     * @return string
     */
    public function get_table_header_class() {
        return 'agreedgrade';
    }

    /**
     * @return string
     */
    public function header_group() {
        return 'grades';
    }
}
