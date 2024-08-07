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

namespace mod_coursework\utils;

global $CFG;
require_once($CFG->libdir.'/editor/atto/lib.php');

class cs_editor extends \atto_texteditor
{
    /**
     *
     * @param $elementid
     * @param $options
     * @param null $fpoptions
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_options($elementid) {
        $options = $this->get_element_options();
        $fpoptions = [];
        $js_plugins = $this->get_js_plugins($elementid, $options, $fpoptions);
        $result = $this->get_init_params($elementid, $options, $fpoptions, $js_plugins);
        return $result;
    }

    /**
     * @return array
     * @throws dml_exception
     */
    protected function get_element_options() {
        global $PAGE;
        $result = [
            'subdirs' => 0,
            'maxbytes' => 0,
            'maxfiles' => 0,
            'changeformat' => 0,
            'areamaxbytes' => FILE_AREA_MAX_BYTES_UNLIMITED,
            'context' => !empty($PAGE->context->id) ? $PAGE->context : context_system::instance(),
            'noclean' => 0,
            'trusttext' => 0,
            'return_types' => 15,
            'enable_filemanagement' => true,
            'removeorphaneddrafts' => false,
            'autosave' => true
        ];
        return $result;
    }

    /**
     * @param $elementid
     * @param $options
     * @param $fpoptions
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    protected function get_js_plugins($elementid, $options, $fpoptions) {
        global $PAGE;
        if (array_key_exists('atto:toolbar', $options)) {
            $configstr = $options['atto:toolbar'];
        } else {
            $configstr = get_config('editor_atto', 'toolbar');
        }

        $grouplines = explode("\n", $configstr);
        $groups = [];

        foreach ($grouplines as $groupline) {
            $line = explode('=', $groupline);
            if (count($line) > 1) {
                $group = trim(array_shift($line));
                $plugins = array_map('trim', explode(', ', array_shift($line)));
                $groups[$group] = $plugins;
            }
        }
        $jsplugins = [];
        foreach ($groups as $group => $plugins) {
            $groupplugins = [];
            foreach ($plugins as $plugin) {
                // Do not die on missing plugin.
                if (!\core_component::get_component_directory('atto_' . $plugin)) {
                    continue;
                }

                // Remove manage files if requested.
                if ($plugin == 'managefiles' && isset($options['enable_filemanagement']) && !$options['enable_filemanagement']) {
                    continue;
                }

                $jsplugin = [];
                $jsplugin['name'] = $plugin;
                $jsplugin['params'] = [];
                $modules[] = 'moodle-atto_' . $plugin . '-button';

                component_callback('atto_' . $plugin, 'strings_for_js');
                $extra = component_callback('atto_' . $plugin, 'params_for_js', array($elementid, $options, $fpoptions));

                if ($extra) {
                    $jsplugin = array_merge($jsplugin, $extra);
                }
                // We always need the plugin name.
                $PAGE->requires->string_for_js('pluginname', 'atto_' . $plugin);
                $groupplugins[] = $jsplugin;
            }
            $jsplugins[] = array('group' => $group, 'plugins' => $groupplugins);
        }
        return $jsplugins;
    }
}
