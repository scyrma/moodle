<?php
// This file is part of Moodle Workplace https://moodle.com/workplace based on Moodle
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
//
// Moodle Workplace Code is dual-licensed under the terms of both the
// single GNU General Public Licence version 3.0, dated 29 June 2007
// and the terms of the proprietary Moodle Workplace Licence strictly
// controlled by Moodle Pty Ltd and its certified premium partners.
// Wherever conflicting terms exist, the terms of the MWL are binding
// and shall prevail.

/**
 * Class cli_helper
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */

namespace tool_wp\local\exportimport;

use tool_tenant\tenancy;
use tool_wp\exporter_base;
use tool_wp\local\exportimport\forms\export_settings_form;
use tool_wp\local\exportimport\forms\import_base_form;
use tool_wp\local\exportimport\helper as exportimport_helper;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/clilib.php');

/**
 * Helper class for CLI export and import
 *
 * @package     tool_wp
 * @copyright   2020 Moodle Pty Ltd <support@moodle.com>
 * @author      2020 Marina Glancy
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @license     Moodle Workplace License, distribution is restricted, contact support@moodle.com
 */
class cli_helper {

    /** @var string */
    const EXPORT = 'export';
    /** @var string */
    const IMPORT = 'import';
    /** @var string */
    protected $operation;
    /** @var array */
    protected $clioptions;
    /** @var int */
    protected $countinputs = 0;
    /** @var exporter_base */
    protected $exporter = null;
    /** @var import_manager */
    protected $importmanager;
    /** @var array to be used in phpunit tests only */
    public $phpunitinputs = [];

    /**
     * cli_helper constructor.
     *
     * @param string $operation self::EXPORT or self::IMPORT
     * @throws \coding_exception
     */
    public function __construct(string $operation) {
        if ($operation === self::EXPORT) {
            $optionsdefinitions = $this->export_options_definitions();
        } else if ($operation === self::IMPORT) {
            $optionsdefinitions = $this->import_options_definitions();
        } else {
            throw new \coding_exception('Unsupported operation');
        }
        $longoptions = [];
        $shortmapping = [];
        foreach ($optionsdefinitions as $key => $option) {
            $longoptions[$key] = $option['default'];
            if (!empty($option['alias'])) {
                $shortmapping[$option['alias']] = $key;
            }
        }

        $this->operation = $operation;
        list($this->clioptions, $this->unrecognized) = cli_get_params(
            $longoptions,
            $shortmapping
        );
    }

    /**
     * Definitions of all available export options
     *
     * @return array[]
     */
    protected function export_options_definitions() {
        $definitions = [
            'non-interactive' => [
                'hasvalue' => false,
                'description' => 'No interactive questions or confirmations, default values are used, script ends with '.
                    'non-zero code if the required settings are missing.',
                'default' => 0,
            ],
            'lang' => [
                'hasvalue' => 'CODE',
                'description' => 'Set preferred language for CLI output. Defaults to the site language if not set. '.
                    'Defaults to \'en\' if the lang parameter is invalid or if the language pack is not installed.',
                'default' => null,
            ],
            'exporter' => [
                'hasvalue' => 'EXPORTER',
                'description' => 'Full name of the exporter class. Required for non-interactive mode.',
                'default' => null,
            ],
            'user' => [
                'hasvalue' => 'USERNAME',
                'description' => 'Username of the user on whose behalf the export will be executed. Highly recommended to '.
                    'specify to make sure the permissions are checked in the right context.',
                'default' => null,
            ],
            'tenant' => [
                'hasvalue' => 'TENANT',
                'description' => 'Database id or non-numeric ID number of the tenant where the export should be made, '.
                    'if not specified the tenant of the user will be used, or the default tenant if no user is specified.',
                'default' => null,
            ],
            'no-tenant' => [
                'hasvalue' => false,
                'description' => 'Perform export without the tenant (only for exporters that support this).',
                'default' => 0,
            ],
            'settings' => [
                'hasvalue' => 'JSON',
                'description' => 'JSON-encoded settings for the exporter.',
                'default' => null,
            ],
            'file' => [
                'hasvalue' => 'PATH',
                'description' => 'Local path to a file (or directory) to save the export.',
                'default' => null,
            ],
            'help' => [
                'hasvalue' => false,
                'description' => 'Print out this help.',
                'default' => 0,
                'alias' => 'h',
            ],
        ];
        return $definitions;
    }

    /**
     * Definitions of all available import options
     *
     * @return array
     */
    protected function import_options_definitions() {
        return [
            'non-interactive' => [
                'hasvalue' => false,
                'description' => 'No interactive questions or confirmations, default values are used, script ends with '.
                    'non-zero code if the required settings are missing.',
                'default' => 0,
            ],
            'lang' => [
                'hasvalue' => 'CODE',
                'description' => 'Set preferred language for CLI output. Defaults to the site language if not set. '.
                    'Defaults to \'en\' if the lang parameter is invalid or if the language pack is not installed.',
                'default' => null,
            ],
            'exportid' => [
                'hasvalue' => 'ID',
                'description' => 'Perform import from a previous export.',
                'default' => null,
            ],
            'file' => [
                'hasvalue' => 'PATH',
                'description' => 'Perform import from a file. Either file or exportid is required.',
                'default' => null,
            ],
            'importer' => [
                'hasvalue' => 'IMPORTER',
                'description' => 'Full name of the importer class. Used for non-Workplace formats (for example CSV).',
                'default' => null,
            ],
            'user' => [
                'hasvalue' => 'USERNAME',
                'description' => 'Username of the user on whose behalf the import will be executed. Highly recommended to '.
                    'specify to make sure the permissions are checked in the right context.',
                'default' => null,
            ],
            'tenant' => [
                'hasvalue' => 'TENANT',
                'description' => 'Database id or non-numeric ID number of the tenant where the import should be made, '.
                    'if not specified the tenant of the user will be used, or the default tenant if no user is specified.',
                'default' => null,
            ],
            'no-tenant' => [
                'hasvalue' => false,
                'description' => 'Perform import without the tenant (only for importers that support this).',
                'default' => 0,
            ],
            'settings' => [
                'hasvalue' => 'JSON',
                'description' => 'JSON-encoded settings for the importer and conflict resolutions.',
                'default' => null,
            ],
            'help' => [
                'hasvalue' => false,
                'description' => 'Print out this help.',
                'default' => 0,
                'alias' => 'h',
            ],
        ];
    }

    /**
     * Display available CLI options as a table
     *
     * @param array $options
     */
    protected function print_help(array $options) {
        $left = [];
        $right = [];
        foreach ($options as $key => $option) {
            if ($option['hasvalue'] !== false) {
                $l = "--$key={$option['hasvalue']}";
            } else if (!empty($option['alias'])) {
                $l = "-{$option['alias']}, --$key";
            } else {
                $l = "--$key";
            }
            $left[] = $l;
            $right[] = $option['description'];
        }
        $this->cli_write('Options:' . PHP_EOL . $this->convert_to_table($left, $right));
    }

    /**
     * Display as CLI table
     *
     * @param array $column1
     * @param array $column2
     * @param int $indent
     * @return string
     */
    protected function convert_to_table(array $column1, array $column2, int $indent = 0) {
        $maxlengthleft = 0;
        $left = [];
        $column1 = array_values($column1);
        $column2 = array_values($column2);
        foreach ($column1 as $i => $l) {
            $left[$i] = str_repeat(' ', $indent) . $l;
            if (strlen('' . $column2[$i])) {
                $maxlengthleft = max($maxlengthleft, strlen($l) + $indent);
            }
        }
        $maxlengthright = 80 - $maxlengthleft - 1;
        $output = '';
        foreach ($column2 as $i => $r) {
            if (!strlen('' . $r)) {
                $output .= $left[$i] . "\n";
                continue;
            }
            $right = wordwrap($r, $maxlengthright, "\n");
            $output .= str_pad($left[$i], $maxlengthleft) . ' ' .
                str_replace("\n", PHP_EOL . str_repeat(' ', $maxlengthleft + 1), $right) . PHP_EOL;
        }
        return $output;
    }

    /**
     * Escape argument to display as a sample command
     *
     * @param mixed $arg
     * @param bool $json
     * @return string|string[]
     */
    protected static function escape_argv($arg, bool $json = false) {
        $arg = $json ? json_encode($arg) : $arg;
        $arg = str_replace('\\', '\\\\', $arg);
        $arg = str_replace('"', '\\"', $arg);
        return preg_match('/^[\w]+$/', $arg) ? $arg : ('"'.$arg.'"');
    }

    /**
     * Print help for export
     */
    public function print_export_help() {
        $this->cli_writeln("Command line Moodle Workplace export.");
        $this->cli_writeln('');
        $this->print_help($this->export_options_definitions());
        $this->cli_writeln('');
        $this->cli_writeln('Example:');
        $this->cli_writeln('$sudo -u www-data /usr/bin/php admin/tool/wp/cli/export.php');
    }

    /**
     * Print help for import
     */
    public function print_import_help() {
        $this->cli_writeln("Command line Moodle Workplace import.");
        $this->cli_writeln('');
        $this->print_help($this->import_options_definitions());
        $this->cli_writeln('');
        $this->cli_writeln('Example:');
        $this->cli_writeln('$sudo -u www-data /usr/bin/php admin/tool/wp/cli/import.php');
    }

    /**
     * Set current language
     */
    public function set_language() {
        global $SESSION;
        if ($this->clioptions['lang']) {
            $SESSION->lang = $this->clioptions['lang'];
        }
    }

    /**
     * Get CLI option
     *
     * @param string $key
     * @return mixed|null
     */
    public function get_cli_option(string $key) {
        return $this->clioptions[$key] ?? null;
    }

    /**
     * Write a text to the given stream
     *
     * @param string $text text to be written
     */
    protected function cli_write($text) {
        if (PHPUNIT_TEST) {
            echo $text;
        } else {
            cli_write($text);
        }
    }

    /**
     * Write error notification
     * @param string $text
     * @return void
     */
    protected function cli_problem($text) {
        if (PHPUNIT_TEST) {
            echo $text;
        } else {
            cli_problem($text);
        }
    }

    /**
     * Print or return section separator string
     * @param bool $return false means print, true return as string
     * @return mixed void or string
     */
    protected function cli_separator($return = false) {
        $separator = cli_separator(true);
        if ($return) {
            return $separator;
        } else {
            $this->cli_write($separator);
        }
    }

    /**
     * Write a text followed by an end of line symbol to the given stream
     *
     * @param string $text text to be written
     */
    protected function cli_writeln($text) {
        $this->cli_write($text . PHP_EOL);
    }

    /**
     * Wrapper for "die()" method so we can unittest it
     *
     * @param mixed $errorcode
     * @throws \moodle_exception
     */
    protected function die($errorcode) {
        if (!PHPUNIT_TEST) {
            die($errorcode);
        } else {
            throw new \moodle_exception('CLI script finished with error code '.$errorcode);
        }
    }

    /**
     * Write to standard error output and exit with the given code
     *
     * @param string $text
     * @param int $errorcode
     * @return void (does not return)
     */
    protected function cli_error($text, $errorcode=1) {
        $this->cli_problem($text);
        $this->die($errorcode);
    }

    /**
     * Get input from user
     *
     * @param string $prompt text prompt, should include possible options
     * @param string $default default value when enter pressed
     * @param array $options list of allowed options, empty means any text
     * @param bool $casesensitiveoptions true if options are case sensitive
     * @return string entered text
     */
    protected function cli_input($prompt, $default = '', array $options = null, $casesensitiveoptions = false) {
        $this->countinputs++;
        if (PHPUNIT_TEST) {
            if (!array_key_exists($this->countinputs - 1, $this->phpunitinputs)) {
                throw new \coding_exception('Unit tests must specify answers to all expected CLI input questions. '.
                    'Asking question: '.$prompt);
            }
            $value = $this->phpunitinputs[$this->countinputs - 1];
            return ($value === null) ? $default : $value;
        } else {
            return cli_input($prompt, $default, $options ?? [], $casesensitiveoptions);
        }
    }

    /**
     * Locate a tenant by numeric id or non-numeric ID number
     *
     * @param int|string $codeorid
     * @param bool $showerror
     * @return \stdClass|null
     */
    protected function locate_tenant($codeorid, $showerror = false): ?\stdClass {
        $tenant = exportimport_helper::locate_tenant($codeorid);
        if (is_null($tenant) && $showerror) {
            $this->cli_writeln(get_string('migrationcannotswitchtenant', 'tool_wp', $codeorid));
        }
        return $tenant;
    }

    /**
     * Is this process interactive?
     *
     * @return bool
     */
    protected function is_interactive(): bool {
        return !$this->get_cli_option('non-interactive');
    }

    /**
     * Validate that user exists and can perform export/import
     *
     * @param string $username
     * @return bool|\stdClass|null
     */
    protected function validate_user(string $username) {
        $interactive = $this->is_interactive();
        $user = \core_user::get_user_by_username($username);
        if (!$user) {
            $this->cli_problem('User with username '.$username.' does not exist');
            $interactive || $this->die(1);
            return null;
        }
        if ($user && !\tool_wp\permission::can_use_export_import($user->id)) {
            $user = null;
            $this->cli_problem('User with username '.$username.' does not have permission to perform export/import');
            $interactive || $this->die(1);
            return null;
        }
        return $user;
    }

    /**
     * Validates specified user and/or prompts for input
     *
     * @return \stdClass
     */
    public function choose_user(): \stdClass {

        $options = $this->clioptions;
        $user = strlen($options['user']) ? $this->validate_user($options['user']) : null;
        $interactive = $this->is_interactive();

        if (!$interactive && empty($user)) {
            $user = get_admin();
            $this->cli_writeln("User not specified, performing export as " . $user->username);
        }

        $defaultusername = get_admin()->username;
        while (!$user) {
            $prompt = ($this->operation === self::EXPORT) ?
                'Perform export as user' : 'Perform import as user';
            $input = $this->cli_input("$prompt [$defaultusername]", $defaultusername);
            $user = $this->validate_user($input);
        }
        return $user;
    }

    /**
     * Choose exporter
     */
    public function choose_exporter() {
        $exporters = \tool_wp\local\exportimport\helper::get_all_exporters();
        $exporter = null;
        $interactive = $this->is_interactive();

        if (!$exporters) {
            $this->cli_error('Sorry, no exporters are available for this user');
        }
        \core_collator::asort_objects_by_method($exporters, 'get_name');
        $exporters = array_values($exporters);

        if ($specifiedexporter = ltrim($this->get_cli_option('exporter'), '\\')) {
            foreach ($exporters as $exp) {
                if (get_class($exp) === $specifiedexporter) {
                    $this->exporter = $exp;
                    return;
                }
            }
            $this->cli_problem('Exporter '.$specifiedexporter.' is either not found or not available');
            $interactive || $this->die(1);
        }

        $expoptions = [];
        foreach ($exporters as $i => $exp) {
            $expoptions[$i + 1] = $exp->get_name();
        }
        $ex = rtrim($this->convert_to_table(array_keys($expoptions), array_values($expoptions), 2));
        $input = $this->cli_input("Select exporter" . PHP_EOL . $ex, '', array_keys($expoptions));
        $this->exporter = $exporters[$input - 1];
    }

    /**
     * Checks if user is allowed to switch to the tenant and displays a problem if not
     *
     * @param \stdClass $tenant
     * @param string $displayname
     * @return bool
     */
    protected function can_switch_to_tenant(\stdClass $tenant, string $displayname) {
        if (\tool_tenant\permission::can_switch_tenant() || $tenant->id == tenancy::get_tenant_id()) {
            return true;
        }
        $this->cli_problem('This user is not allowed to switch to tenant ' . $displayname);
        return false;
    }

    /**
     * Validate the parameters for tenant and/or ask for user input if necessary
     *
     * @return \stdClass
     */
    public function choose_tenant(): ?\stdClass {
        if ($this->get_cli_option('tenant') && $this->get_cli_option('no-tenant')) {
            $this->cli_error('Options --tenant and --no-tenant can not be used together');
        }

        if ($this->operation === self::EXPORT) {
            $notenantpossible = !$this->exporter->is_tenant_required();
        } else {
            $notenantpossible = !$this->importmanager->get_importer()->is_tenant_required();
        }
        $interactive = $this->is_interactive();
        $tenants = tenancy::get_tenants();

        if ($this->get_cli_option('no-tenant')) {
            if ($notenantpossible) {
                return null;
            } else if ($this->operation === self::EXPORT) {
                $this->cli_problem('Exporter \''.$this->exporter->get_name().'\' requires a tenant');
            } else {
                $this->cli_problem('Importer \''.$this->importmanager->get_importer()->get_name().'\' requires a tenant');
            }
            $interactive || $this->die(1);
        }

        $usertenant = $tenants[tenancy::get_tenant_id()];
        if (!tenancy::is_site_multi_tenant()) {
            return $notenantpossible ? null : $usertenant;
        }

        $defaulttenant = (strlen($usertenant->idnumber) && !is_number($usertenant->idnumber)) ?
            $usertenant->idnumber : $usertenant->id;
        if ($selectedtenant = $this->get_cli_option('tenant')) {
            if ($tenant = $this->locate_tenant($selectedtenant, true)) {
                if (!$this->can_switch_to_tenant($tenant, $selectedtenant)) {
                    $interactive || $this->die(1);
                } else {
                    return $tenant;
                }
            } else {
                $interactive || $this->die(1);
            }
        }

        if (!\tool_tenant\permission::can_switch_tenant()) {
            return $usertenant;
        } else if (!$interactive) {
            $this->cli_writeln("Tenant not specified, assuming user tenant");
            return $usertenant;
        }

        while (true) {
            $prompt = ($this->operation === self::EXPORT) ?
                'Select export tenant' : 'Select destination tenant';
            $input = $this->cli_input("$prompt ".
                ($notenantpossible ? "('-' for 'No tenant') " : '') . "[$defaulttenant]", $defaulttenant);
            if ($input === '-' && $notenantpossible) {
                return null;
            } else if ($tenant = $this->locate_tenant($input, true)) {
                if ($this->can_switch_to_tenant($tenant, $input)) {
                    return $tenant;
                }
            }
        }
    }

    /**
     * Summary of export
     *
     * @param \stdClass|null $tenant
     */
    public function print_export_summary(?\stdClass $tenant) {
        global $USER;
        $this->cli_separator();
        $this->cli_writeln("Perform export as: " . $USER->username);
        $this->cli_writeln("Exporter: ".$this->exporter->get_name() . ' ('.get_class($this->exporter).')');
        $this->cli_writeln("Tenant: ". ($tenant ? $tenant->name : '-'));
        $this->cli_writeln("Export settings:" . PHP_EOL . '  ' . join(PHP_EOL . '  ', $this->get_exporter_settings_for_display()));
        if ($this->is_interactive()) {
            $this->cli_writeln('');
            $this->cli_writeln("Available settings:" . PHP_EOL . $this->get_exporter_settings_help(2));
        }
        if ($this->countinputs || !$this->get_exporter_settings(false)) {
            $this->cli_writeln('');
            $this->cli_writeln('Example of performing this export from CLI:');
            $command = 'php admin/tool/wp/cli/export.php';
            $command .= ' --user='.$USER->username;
            $command .= ' --exporter='.self::escape_argv(get_class($this->exporter));
            if (tenancy::is_site_multi_tenant()) {
                if ($tenant) {
                    $command .= ' --tenant='.($tenant->idnumber ?: $tenant->id);
                } else {
                    $command .= ' --no-tenant';
                }
            }
            $command .= ' --settings='.self::escape_argv($this->get_exporter_settings(), true);
            $this->cli_writeln('  '.$command);
        }
        $this->cli_separator();
    }

    /**
     * Prepare export settings form
     *
     * @param array $submitteddata
     * @param \stdClass|null $tenant
     * @return export_settings_form
     */
    protected function prepare_export_form(array $submitteddata = [], ?\stdClass $tenant = null): export_settings_form {
        global $USER;
        $formdata = [
            'exporter' => get_class($this->exporter),
            'exportertenant' => $tenant->id ?? 0,
            'entrypoint' => '',
            'entrypointid' => 0,
        ] + $submitteddata;
        $oldignoresesskey = $USER->ignoresesskey ?? null;
        $USER->ignoresesskey = true;
        $formidentifier = str_replace('\\', '_', export_settings_form::class);
        $formdata['_qf__' . $formidentifier] = 1;
        $form = new export_settings_form(null, null, 'post', '', [], true, $formdata, true);
        $USER->ignoresesskey = $oldignoresesskey;

        $form->set_data_for_modal();
        return $form;
    }

    /**
     * Validate options and perform export
     *
     * @param \stdClass|null $tenant
     * @return int export id (dies if there is any problem with export)
     */
    public function perform_export(?\stdClass $tenant = null): int {
        list($dirname, $filename) = $this->get_export_destination();
        $form = $this->prepare_export_form($this->get_exporter_settings(false), $tenant);

        if (!$form->is_validated()) {
            $errors = $form->get_quick_form()->_errors;
            $this->cli_problem('Validation failed:' . PHP_EOL .
                $this->convert_to_table(array_keys($errors), array_values($errors), 2));
            $this->die(1);
        }

        $alldata = $form->get_data();
        $exporterclass = get_class($this->exporter);
        $entrypoint = $alldata->entrypoint ?? '';
        $entrypointid = $alldata->entrypointid ?? 0;
        $exporter = export_manager::create_exporter($exporterclass, $entrypoint, $entrypointid, (array)$alldata,
            $tenant->id ?? 0);
        $instancesobjects = export_manager::get_instances_for_review_form($exporter);
        $instances = helper::display_instances_list_cli($instancesobjects);
        if (!$instances) {
            $this->cli_problem('Nothing to export');
            $this->die(1);
        }
        $this->cli_writeln('Will be exported:'.PHP_EOL.'  '.join(PHP_EOL.'  ', $instances));

        if ($this->is_interactive()) {
            $this->cli_writeln('');
            $answer = $this->cli_input('Continue [Y/n]?', 'y', ['y', 'n', 'yes', 'no']);
            if (strtolower(substr($answer, 0, 1)) === 'n') {
                $this->die(1);
            }
        }

        $this->cli_separator();
        $this->cli_writeln('Starting export...');
        $exportid = export_manager::schedule_export((array)$alldata, false);
        $exportmanager = new export_manager($exportid);
        $exportmanager->perform_export();
        $status = $exportmanager->get_export_status();
        $statusstr = \tool_wp\local\exportimport\helper::format_export_import_status($exportmanager->get_export_status());
        $this->cli_writeln('... export completed with status: '.$statusstr);
        $this->cli_writeln('Export id: '.$exportid);
        if ($status == helper::STATUS_DONE) {
            $url = export_manager::get_export_file_url($exportid)->out(false);
            $this->cli_writeln('Download at: '.$url);
        } else {
            $this->die(1);
        }
        $this->save_export_file($exportid, $dirname, $filename);
        return $exportid;
    }

    /**
     * Get supplied exporter options
     *
     * @param bool $withdefaults
     * @return array|mixed
     */
    protected function get_exporter_settings(bool $withdefaults = true) {
        $settingsjson = $this->get_cli_option('settings');
        $settings = $settingsjson ? (@json_decode($settingsjson, true) ?: []) : [];
        if ($withdefaults) {
            $settings += $this->get_exporter_settings_defaults();
        }
        return $settings;
    }

    /**
     * Get exporter options for display
     *
     * @return array
     */
    protected function get_exporter_settings_for_display(): array {
        $settings = $this->get_exporter_settings(true);
        $displaysettings = [];
        foreach ($settings as $key => $value) {
            if ($value === false) {
                $value = 0;
            } else if (is_array($value)) {
                $value = json_encode($value);
            }
            $displaysettings[] = "$key=$value";
        }
        return $displaysettings;
    }

    /**
     * Get available exporter settings (for help)
     *
     * @param int $indent
     * @return string
     */
    protected function get_exporter_settings_help(int $indent = 0): string {
        $settings = $this->prepare_export_form()->get_form_elements_cli_help();
        return $this->convert_to_table(array_column($settings, 0), array_column($settings, 1), $indent);
    }

    /**
     * Returns an array of available settings
     *
     * @return array key is the option (element) name and value is a default value
     */
    protected function get_exporter_settings_defaults(): array {
        return $this->prepare_export_form()->get_elements_defaults_for_cli();
    }

    /**
     * Where to save result
     *
     * @return array array [$dirname, $filename]
     */
    protected function get_export_destination(): array {
        if (!$filepath = $this->get_cli_option('file')) {
            return [null, null];
        }
        if (is_dir($filepath)) {
            if (!is_writable($filepath)) {
                $this->cli_error('Directory ' . $filepath . ' is not writable');
            }
            return [$filepath, null];
        } else if (file_exists($filepath)) {
            $this->cli_error('File ' . $filepath . ' already exists');
        } else {
            $dir = dirname($filepath);
            if (!is_dir($dir)) {
                $this->cli_error('Directory ' . $dir . ' does not exist');
            } else if (!is_writable($dir)) {
                $this->cli_error('Directory ' . $filepath . ' is not writable');
            }
            return [$dir, basename($filepath)];
        }
    }

    /**
     * Save export to a local file
     *
     * @param int $exportid
     * @param string $dirname
     * @param string $filename
     */
    protected function save_export_file(int $exportid, ?string $dirname, ?string $filename) {
        if (strlen($dirname)) {
            $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'tool_wp', 'export', $exportid, '', false);
            $file = reset($files);
            if ($filename === null) {
                $filename = $file->get_filename();
            }
            $filepath = $dirname . '/' . $filename;
            if ($file->copy_content_to($filepath)) {
                $this->cli_writeln("Export saved to: " . realpath($filepath));
            } else {
                $this->cli_error("Error occurred while saving export file to " . $filepath);
            }
        }
    }

    /**
     * Check that either exportid or file is specified
     */
    public function check_import_source() {
        global $DB;
        $exportid = (int)$this->get_cli_option('exportid');
        if (strlen($exportid) && !is_numeric($exportid)) {
            $this->cli_error('Parameter exportid must be a number');
        }
        $file = $this->get_cli_option('file');
        if ($exportid && $file) {
            $this->cli_error('Please specify either --exportid or --file but not both.');
        }
        if (!$exportid && !$file) {
            $this->cli_error('Either --exportid or --file must be specified.');
        }
        if ($exportid && !$DB->record_exists('tool_wp_export', ['id' => $exportid])) {
            $this->cli_error('Export with id '.$exportid.' was not found');
        }
        if ($file && (!file_exists($file) || !is_readable($file))) {
            $this->cli_error('File '.$file.' does not exist or is not readable');
        }
    }

    /**
     * Start importing: Save file to filestorage, create instance of import_manager
     */
    public function start_importing() {
        global $USER;
        $exportid = $this->get_cli_option('exportid');
        if ($exportid && !\tool_wp\permission::can_view_export($exportid)) {
            $this->cli_error('Export with id '.$exportid.' exists but user ' . $USER->username . ' can not access it.');
        }

        $settings = [];
        if ($exportid) {
            $this->importmanager = import_manager::create_import_from_exportfile($settings, $exportid);
        } else if ($file = $this->get_cli_option('file')) {
            $fs = get_file_storage();
            $itemid = file_get_unused_draft_itemid();
            $usercontext = \context_user::instance($USER->id);
            $fs->create_file_from_pathname(['component' => 'user', 'filearea' => 'draft',
                'contextid' => $usercontext->id, 'itemid' => $itemid, 'filepath' => '/',
                'filename' => basename($file)], $file);
            $this->importmanager = import_manager::create_import_from_draftfile($settings, $itemid);
        }
    }

    /**
     * Set up importmanager and choose importer
     */
    public function choose_importer() {
        $importers = $this->importmanager->get_importers();
        if (!$importers) {
            $this->cli_error('No importers found');
        }

        $interactive = $this->is_interactive();
        \core_collator::asort_objects_by_method($importers, 'get_name');
        $importers = array_values($importers);

        if ($specifiedimporter = ltrim($this->get_cli_option('importer'), '\\')) {
            foreach ($importers as $imp) {
                if (get_class($imp) === $specifiedimporter) {
                    $this->importmanager->save_general_settings(['importer' => get_class($imp)]);
                    return;
                }
            }
            $this->cli_problem('Importer '.$specifiedimporter.' is either not found or not available');
            $interactive || $this->die(1);
        }

        if (count($importers) == 1 && empty($specifiedimporter)) {
            $importer = reset($importers);
            $this->importmanager->save_general_settings(['importer' => get_class($importer)]);
            return;
        } else if (!$interactive) {
            $this->cli_error('More than one importer is available, --importer must be specified');
        }

        $impoptions = [];
        foreach ($importers as $i => $imp) {
            $impoptions[$i + 1] = $imp->get_name();
        }
        $ex = rtrim($this->convert_to_table(array_keys($impoptions), array_values($impoptions), 2));
        $input = $this->cli_input("Select importer" . PHP_EOL . $ex, '', array_keys($impoptions));
        $this->importmanager->save_general_settings(['importer' => get_class($importers[$input - 1])]);
    }

    /**
     * Imported file summary
     */
    public function print_imported_file_summary() {
        $summary = $this->importmanager->get_export_file_summary();
        if (!$summary) {
            return;
        }
        $this->cli_separator();
        foreach ($summary as $key => $data) {
            if ($key === 'content') {
                $value = '';
                foreach ($data['value'] as $idx => $c) {
                    $value .= '  ' . $c . PHP_EOL;
                }
            } else {
                $value = '  ' . $data['value'] . PHP_EOL;
            }
            $this->cli_writeln($data['label']);
            $this->cli_write($value);
        }
        $this->cli_separator();
    }

    /**
     * Apply import general settings
     *
     * @param \stdClass|null $tenant
     */
    public function apply_general_settings(?\stdClass $tenant) {
        if ($this->operation === self::EXPORT) {
            if ($tenant && $tenant->id != \tool_tenant\tenancy::get_tenant_id()) {
                \tool_tenant\tenancy::set_switched_tenant_id($tenant->id);
            }
        } else if ($this->operation === self::IMPORT) {
            $this->importmanager->save_general_settings(['tenantid' => $tenant ? $tenant->id : 0]);
            $this->importmanager->retrieve_and_save_review_data();
        }
    }

    /**
     * Prepare import form
     *
     * @param int $step
     * @param array $submitteddata
     * @return import_base_form
     * @throws \coding_exception
     */
    protected function prepare_import_form(int $step, array $submitteddata = []): import_base_form {
        global $USER;

        /** @var import_base_form $formclass */
        $formclass = null;
        if ($step == 2) {
            $formclass = \tool_wp\local\exportimport\forms\import_general_form::class;
        } else if ($step == 3) {
            $formclass = \tool_wp\local\exportimport\forms\import_settings_form::class;
        } else if ($step == 4) {
            $formclass = \tool_wp\local\exportimport\forms\import_conflict_form::class;
        } else if ($step == 5) {
            $formclass = \tool_wp\local\exportimport\forms\import_review_form::class;
        } else {
            throw new \coding_exception('Form step must be between 2 and 5');
        }
        $submitteddata['importid'] = $this->importmanager->get_import_id();

        // Now mock the form submission.
        $formidentifier = str_replace('\\', '_', $formclass);
        $submitteddata['_qf__' . $formidentifier] = 1;
        $oldignoresesskey = $USER->ignoresesskey ?? null;
        $USER->ignoresesskey = true;
        /** @var import_base_form $form */
        $form = new $formclass(null, null, 'post', '', [], true, $submitteddata, true);
        $USER->ignoresesskey = $oldignoresesskey;

        $form->set_data_for_modal();
        return $form;
    }

    /**
     * Get available importer settings (for help)
     *
     * @param int $indent
     * @return string
     */
    protected function get_import_settings_help(int $indent = 0): string {
        $settings = $this->prepare_import_form(3)->get_form_elements_cli_help();
        return $this->convert_to_table(array_column($settings, 0), array_column($settings, 1), $indent);
    }

    /**
     * Returns an array of default settings
     *
     * @return array key is the option (element) name and value is a default value
     */
    protected function get_import_settings_defaults(): array {
        return $this->prepare_import_form(3)->get_elements_defaults_for_cli();
    }

    /**
     * Returns an array of default conflict resolution settings
     *
     * @return array key is the option (element) name and value is a default value
     */
    protected function get_import_conflict_settings_defaults(): array {
        return $this->prepare_import_form(4)->get_elements_defaults_for_cli();
    }

    /**
     * Get supplied import settings
     *
     * @return array|mixed
     */
    protected function get_import_settings() {
        $settingsjson = $this->get_cli_option('settings');
        $settings = $settingsjson ? (@json_decode($settingsjson, true) ?: []) : [];
        return $settings;
    }

    /**
     * Prepare import settings to be displayed on the screen
     *
     * @return array
     */
    protected function get_import_settings_for_display() {
        $defaults = $this->get_import_settings_defaults();
        $settings = array_intersect_key($this->get_import_settings() + $defaults, $defaults);
        $displaysettings = [];
        foreach ($settings as $key => $value) {
            if ($value === false) {
                $value = 0;
            } else if (is_array($value)) {
                $value = json_encode($value);
            }
            $displaysettings[] = "$key=$value";
        }
        return $displaysettings;
    }

    /**
     * Prepare import conflict settings to be displayed on the screen
     *
     * @return array
     */
    protected function get_import_conflict_settings_for_display() {
        $defaults = $this->get_import_conflict_settings_defaults();
        $settings = $this->get_import_settings();
        $settings = array_intersect_key($settings + $defaults, $defaults);
        $displaysettings = [];
        foreach ($settings as $key => $value) {
            if ($value === false) {
                $value = 0;
            } else if (is_array($value)) {
                $value = json_encode($value);
            }
            $displaysettings[] = "$key=$value";
        }
        return $displaysettings;
    }

    /**
     * Summary of import
     */
    public function print_import_summary() {
        global $USER;

        if ($tenantid = $this->importmanager->get_import_tenant_id()) {
            $tenant = tenancy::get_tenants()[$tenantid];
        } else {
            $tenant = null;
        }

        $this->cli_separator();
        $this->cli_writeln("Perform import as: " . $USER->username);
        $this->cli_writeln("Importer: ".$this->importmanager->get_importer()->get_name() .
            ' ('.get_class($this->importmanager->get_importer()).')');
        $this->cli_writeln("Tenant: ". ($tenant ? tenancy::get_tenant_name_from_id($tenant->id) : '-'));
        $this->cli_writeln("Import settings:" . PHP_EOL . '  ' . join(PHP_EOL . '  ', $this->get_import_settings_for_display()));
        if ($this->is_interactive()) {
            $this->cli_writeln('');
            $this->cli_write("Available settings:" . PHP_EOL . $this->get_import_settings_help(2));
        }
        $this->cli_separator();
    }

    /**
     * Display a warning that some settings were ignored or modified
     *
     * @param array $supplied
     * @param array $cleaned
     */
    protected function notify_about_modified_settings(array $supplied, array $cleaned) {
        $modified = $ignored = [];
        foreach ($supplied as $key => $value) {
            if (!array_key_exists($key, $cleaned)) {
                $ignored[$key] = "$key=".json_encode($value);
            } else if (json_encode($value) !== json_encode($cleaned[$key])) {
                $modified[$key] = "$key=".json_encode($value) . ' - assuming '.
                    json_encode($cleaned[$key]);
            }
        }
        if ($ignored) {
            $this->cli_problem("Some of the supplied settings were ignored: " . PHP_EOL . '  ' .
                join(PHP_EOL . '  ', $ignored) . PHP_EOL);
        }
        if ($modified) {
            $this->cli_problem('Some of the supplied settings are not valid:' . PHP_EOL . '  ' .
                join(PHP_EOL . '  ', $modified) . PHP_EOL);
            if (!$this->is_interactive()) {
                $this->cli_error('Terminating non-interactive execution due to invalid settings');
            }
        }
        if ($ignored || $modified) {
            $this->cli_separator();
        }
    }

    /**
     * Print command line example
     *
     * @param array $settingsfordisplay
     */
    public function print_import_command_example(array $settingsfordisplay) {
        global $USER;

        $this->notify_about_modified_settings($this->get_import_settings(), $settingsfordisplay);

        if (!$this->countinputs && $this->get_import_settings()) {
            // User already supplied everything, no need to show example import.
            // TODO show if there are conflicts but no conflicts settings.
            return;
        }

        if ($tenantid = $this->importmanager->get_import_tenant_id()) {
            $tenant = tenancy::get_tenants()[$tenantid];
        } else {
            $tenant = null;
        }

        $this->cli_writeln('Example of performing this import from CLI:');
        $command = 'php admin/tool/wp/cli/import.php';
        if ($exportid = $this->get_cli_option('exportid')) {
            $command .= ' --exportid='.$exportid;
        } else if ($path = $this->get_cli_option('file')) {
            $command .= ' --file='.self::escape_argv($path);
        }
        $command .= ' --importer='.self::escape_argv(get_class($this->importmanager->get_importer()));
        $command .= ' --user='.$USER->username;
        if (tenancy::is_site_multi_tenant()) {
            if ($tenant) {
                $command .= ' --tenant='.($tenant->idnumber ?: $tenant->id);
            } else {
                $command .= ' --no-tenant';
            }
        }
        $command .= ' --settings='.self::escape_argv($settingsfordisplay, true);
        $this->cli_writeln('  '.$command);

        $this->cli_separator();
    }

    /**
     * Apply import settings
     */
    public function apply_import_settings() {
        $defaults1 = $this->get_import_settings_defaults();
        $form = $this->prepare_import_form(3, $this->get_import_settings() + $defaults1);

        if (!$form->is_validated()) {
            $errors = $form->get_quick_form()->_errors;
            $this->cli_problem('Validation failed:' . PHP_EOL .
                $this->convert_to_table(array_keys($errors), array_values($errors), 2));
            $settingsfordisplayfailed = array_intersect_key($this->get_import_settings(), $defaults1) + $defaults1;
            $this->print_import_command_example($settingsfordisplayfailed);
            $this->die(1);
        }

        $settings = array_diff_key((array)$form->get_data(), ['importid' => 1, 'stage' => 1]);
        $settingsfordisplay = array_intersect_key((array)$form->get_data(), $defaults1) + $defaults1;
        $this->importmanager->save_settings($settings);

        if (!$this->importmanager->has_conflicts()) {
            $this->cli_writeln('No conflicts found!');
        } else {
            $defaults2 = $this->importmanager->has_conflicts() ? $this->get_import_conflict_settings_defaults() : [];

            $this->cli_writeln("Import conflict resolutions settings:" . PHP_EOL . '  ' .
                join(PHP_EOL . '  ', $this->get_import_conflict_settings_for_display()));
            if ($this->is_interactive()) {
                $this->cli_writeln('');
                $settings = $this->prepare_import_form(4)->get_form_elements_cli_help();
                $table = $this->convert_to_table(array_column($settings, 0), array_column($settings, 1), 2);
                $this->cli_write("Available conflict resolutions settings:" . PHP_EOL . $table);
            }

            $form = $this->prepare_import_form(4, $this->get_import_settings());

            if (!$form->is_validated()) {
                $errors = $form->get_quick_form()->_errors;
                $this->cli_problem('Validation failed:' . PHP_EOL .
                    $this->convert_to_table(array_keys($errors), array_values($errors), 2));
                $settingsfordisplay += array_intersect_key($this->get_import_settings(), $defaults2) + $defaults2;
                $this->print_import_command_example($settingsfordisplay);
                $this->die(1);
            }

            $settings = array_diff_key((array)$form->get_data(), ['importid' => 1, 'stage' => 1]);
            $settingsfordisplay += array_intersect_key($settings, $defaults2) + $defaults2;
            $this->importmanager->save_settings($settings);
        }

        $this->cli_separator();
        $this->print_import_command_example($settingsfordisplay);
    }

    /**
     * Perform import
     */
    public function perform_import(): int {
        $instances = array_column(array_filter($this->importmanager->get_instances(), function($inst) {
            return $inst['id'] != 0;
        }), 'instancename');
        if (!$instances) {
            $this->cli_error('Nothing to import!');
        }
        $this->cli_writeln('Instances to be imported ('.count($instances).'):'.PHP_EOL.'  '.join(PHP_EOL.'  ', $instances));
        $this->cli_separator();

        if ($this->is_interactive()) {
            $this->cli_writeln('');
            $answer = $this->cli_input('Continue [Y/n]?', 'y', ['y', 'n', 'yes', 'no']);
            if (strtolower(substr($answer, 0, 1)) === 'n') {
                $this->die(1);
            }
        }

        $importid = $this->importmanager->get_import_id();
        $this->importmanager->schedule_import(false);
        $this->importmanager->perform_import();

        $this->cli_separator();
        $this->cli_writeln('Starting import...');
        $this->importmanager->schedule_import(false);
        $this->importmanager->perform_import();
        $status = $this->importmanager->get_import_status();
        $statusstr = helper::format_export_import_status($status);
        $this->cli_writeln('... import completed with status: '.$statusstr);
        $this->cli_writeln('Import id: '.$importid);
        if ($status == helper::STATUS_DONE) {
            $url = helper::import_url($importid);
            $this->cli_writeln('View at: '.$url->out(false));
        } else {
            $this->die(1);
        }
        return $importid;
    }
}
