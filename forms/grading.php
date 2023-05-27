<?php

/**
 * Edwiser grading form
 *
 * @package    local_edwisersubmission
 * @copyright  2022 Jennifer Aube <jennifer.aube@civicactions.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/formslib.php");

class edwisersubmission_form extends moodleform
{
    //Add elements to form
    public function definition()
    {
        global $CFG;
        global $USER;

        $mform = $this->_form;
//        $mform->addElement('html', '<h3>Actions</h3>');
        $options = array(
            'Approved' => 'Approve',
            'Needs Action' => 'Needs action'
        );
        $mform->addElement('select', 'status', 'Change Status:', $options);
        $buttonarray=array();
        $buttonarray[] = $mform->createElement('submit', 'Save', 'Save Changes');
        $mform->addgroup($buttonarray, 'buttonar', '', ' ', false);

    }
    //Custom validation should be added here
    public function validation($data, $files)
    {
        return array();
    }
}