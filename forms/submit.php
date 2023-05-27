<?php

/**
 * Edwiser grading form
 *
 * @package    local_edwisersubmission
 * @copyright  2022 Jennifer Aube <jennifer.aube@civicactions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class edwisersubmissionsubmit_form extends moodleform
{
    //Add elements to form
    public function definition()
    {
        global $CFG;
        global $USER;

        $mform = $this->_form;
        $buttonarray=array();
        $buttonarray[] = $mform->createElement('submit', 'Submitted', 'Submit for feedback');
        $mform->addgroup($buttonarray, 'buttonar', '', ' ', false);


    }
    //Custom validation should be added here
    public function validation($data, $files)
    {
        return array();
    }
}