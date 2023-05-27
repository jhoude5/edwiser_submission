<?php

require_once("$CFG->libdir/formslib.php");

class local_file_attachment_form extends moodleform
{
    //Add elements to form
    public function definition()
    {
        global $CFG, $USER, $DB;

        $mform = $this->_form;
        $mform->addElement('html', '<div class="star--file-attachments">');
        $mform->addElement('filemanager', 'attachments', '', null,
            array('subdirs' => 0, 'maxbytes' => 2097152, 'areamaxbytes' => -1, 'maxfiles' => -1,
                'accepted_types' => array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt')));
        $mform->addElement('html', '<span class="accepted-files">Accepted file types:<strong> pdf doc docx xls xlsx txt</strong></span>
            </div>');

        $this->add_action_buttons(false, 'Upload');



    }
    //Custom validation should be added here
    public function validation($data, $files)
    {
        return array();
    }
}