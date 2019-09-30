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
 * entity class.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

namespace tool_datastore;

use core\persistent;
use tool_datastore\actions\base;

defined('MOODLE_INTERNAL') || die;

/**
 * entity class.
 *
 * @package   tool_datastore
 * @copyright 2018 Moodle Pty Ltd <support@moodle.com>
 * @author    2018, Alberto Lara Hernández <albertolara@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */
class entity extends persistent {
    /** Table name for the persistent. */
    const TABLE = 'tool_datastore_entity';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'actionid' => array(
                'type' => PARAM_INT
            ),
            'type' => array(
                'type' => PARAM_TEXT
            ),
            'snapshotid' => array(
                'type' => PARAM_INT
            ),
            'originalid' => array(
                'type' => PARAM_INT
            )
        );
    }

    /**
     * Create an entity.
     *
     * @param int $datastoreeventid
     * @param int $originalid
     * @param string $type
     *
     * @return mixed
     */
    public static function create_entity(int $datastoreeventid, int $originalid, string $type) {
        if ($type === 'course' && 0 === $originalid) {
            return;
        }
        try {
            $data = new \stdClass();
            $data->actionid = $datastoreeventid;
            $data->originalid = $originalid;
            $data->snapshotid = -1;
            $data->type = $type;
            $persistent = new entity(0, $data);
            $result = $persistent->create();
            return $result->get('id');
        } catch (\Exception $ex) {
            debugging('Error create_entity: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }

    /**
     * When a new entity is created We need to store the entity snapshot and the fields.
     */
    protected function after_create() {
        $function = '\tool_datastore\api::get_' . $this->get('type');
        $datatosnapshot = $function($this->get('originalid'));
        $this->store_indexed_fields($this->get('type'), $datatosnapshot);
        $snapshotid = $this->make_snapshot($datatosnapshot);
        $this->set('snapshotid', $snapshotid)->update();
    }

    /**
     * Store the fields from the entity.
     * ¡
     * @param string $typeofentity
     * @param array $datatosnapshot
     */
    protected function store_indexed_fields(string $typeofentity, array $datatosnapshot) {
        try {
            $fields = $this->get_fields_to_index();
            $fieldstoindex = explode(',', $fields[$typeofentity]);
            foreach ($fieldstoindex as $field) {
                if (is_array($datatosnapshot[$field])) {
                    foreach ($datatosnapshot[$field] as $keysubfield => $subfield) {
                        $this->store_field(
                            $this->get('id'),
                            $this->get_related_action_id(),
                            "$field:$keysubfield",
                            $subfield
                        );
                    }
                } else {
                    $this->store_field(
                        $this->get('id'),
                        $this->get_related_action_id(),
                        $field,
                        $datatosnapshot[$field]
                    );
                }
            }
        } catch (\Exception $ex) {
            debugging('Error store_indexed_fields: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }

    /**
     * Store a field.
     *
     * @param int $entityid
     * @param int $actionid
     * @param string $field
     * @param string $value
     */
    protected function store_field(int $entityid, int $actionid, string $field, string $value) {
        $data = new \stdClass();
        $data->entityid = $entityid;
        $data->actionid = $actionid;
        $data->name = $field;
        $data->value = $value;
        $persistent = new fields(0, $data);
        $persistent->save();
    }

    /**
     * Store the snapshot of the entity
     *
     * @param array $datatosnapshot
     *
     * @return int
     */
    protected function make_snapshot(array $datatosnapshot) : int {
        try {
            $data = new \stdClass();
            $encodedata = json_encode($datatosnapshot);
            $data->data = $encodedata;
            $data->hash = md5($encodedata);
            $persistent = new snapshot(0, $data);
            return $persistent->create()->get('id');
        } catch (\Exception $ex) {
            debugging('Error make_snapshot: '.$ex->getMessage(), DEBUG_NORMAL, $ex->getTrace());
        }
    }

    /**
     * Get the fields from entity to index.
     *
     * @return array
     * @throws \coding_exception
     */
    protected function get_fields_to_index() : array {
        $action = new action($this->get('actionid'));
        $event = $action->get('action');
        $class = '\\tool_datastore\\action\\'.$event.'::get_fields_to_index';
        return $class();
    }

    /**
     * Get the related action id.
     *
     * @return int
     * @throws \coding_exception
     */
    protected function get_related_action_id() : int {
        $action = new action($this->get('actionid'));
        return $action->get('id');
    }
}

