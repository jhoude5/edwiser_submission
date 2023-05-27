<?php

require_once("$CFG->libdir/formslib.php");

class local_remove_edwiser_form extends moodleform
{
    //Add elements to form
    public function definition()
    {
        global $CFG, $USER, $DB;

        $mform = $this->_form;
        $userid = $USER->id;


        $mform->addElement('html', '<div class="border remove-submission"><h3 class="px-4">Confirm</h3>
            <p class="border-top border-bottom p-4">Are you sure you want to remove the submission data?</p>
            <div class="remove-submission--buttons">');

        $this->add_action_buttons(true, 'Continue');
        $mform->addElement('html', '</div></div>');

    }
    //Custom validation should be added here
    public function validation($data, $files)
    {
        return array();
    }
}