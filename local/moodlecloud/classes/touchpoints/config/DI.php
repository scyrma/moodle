<?php declare(strict_types=1);
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
 * Dependancy injection configuration.
 *
 * @package    local_moodlecloud
 * @copyright  2017 Cameron Ball <cameron@cameron1729.xyz>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_moodlecloud;
defined('MOODLE_INTERNAL') || die();

use DateTimeImmutable;
use Exception;
use curl;
use local_moodlecloud\common\computation_result;
use local_moodlecloud\common\functions;
use local_moodlecloud\notifications\notification;
use local_moodlecloud\notifications\notification_repository;
use local_moodlecloud\touchpoints\resolvers\action_resolver;
use local_moodlecloud\touchpoints\resolvers\criterion_resolver;
use local_moodlecloud\touchpoints\touchpoint;
use local_moodlecloud\touchpoints\touchpoint_factory;
use local_moodlecloud\touchpoints\touchpoint_repository;
use moodle_database;
use stdClass;

global $DB, $CFG;

return [
    moodle_database::class => $DB,

    criterion_resolver::class => function($container)  : criterion_resolver {
        return new criterion_resolver($container->get('touchpoints.criteriaNamespace'));
    },

    action_resolver::class => function($container) : action_resolver {
        return new action_resolver(
            $container->get('touchpoints.actionsNamespace'),
            $container->get('touchpoints.callables.getSignupCallable'),
            $container->get('touchpoints.callables.getNotificationCallable')
        );
    },

    touchpoint_factory::class => function($container) : touchpoint_factory {
        return new touchpoint_factory(
            $container->get(criterion_resolver::class),
            $container->get(action_resolver::class)
        );
    },

    touchpoint_repository::class => function($container) : touchpoint_repository {
        return new touchpoint_repository(
            $container->get(moodle_database::class),
            $container->get(touchpoint_factory::class),
            $container->get('touchpoints.callables.filterPermittedTouchpointActions'),
            (include $container->get('touchpoints.definitions'))
        );
    },

    'touchpoints.definitions' => $CFG->dirroot . '/local/moodlecloud/classes/touchpoints/config/touchpoint_definitions.php',
    'touchpoints.maxAttempts' => 3,
    'touchpoints.criteriaNamespace' => '\\local_moodlecloud\\touchpoints\\criteria',
    'touchpoints.actionsNamespace' => '\\local_moodlecloud\\touchpoints\\actions',

    // Below are a bunch of "callables". Touchpoints are processed by first getting a list
    // of touchpoint objects and then piping that list through a sequence of steps
    // (e.g., filtering out runnable touchpoints, excluding certain touchpoints based on
    // user preference, persistance, etc). Each of these callables can be considered a
    // consise step in the computation. They typically accept and return an array so that
    // they may be composed together (ideally the type should be stricter than simply array).

    // A function that knows how to call signup using the helper from auth_moodlecloud.
    // Sadly the call method returns particularily unuseful information like true or null
    // but it does under some circumstances throw exceptions, so the easiest way to
    // capture something vaguely useful is to just throw an exception and make use of
    // the try_catch function in common/functions in calling code.
    'touchpoints.callables.getSignupCallable' => function($container) : callable {
        return function(array $parameters) : callable {
            return function() use ($parameters) : bool {
                if (!\auth_moodlecloud\helper::call('touchpoint', $parameters)) {
                    throw new Exception('Signup API call failure');
                }

                return true;
            };
        };
    },

    // A function that knows how to create an admin notification.
    'touchpoints.callables.getNotificationCallable' => function($container) : callable {
        return function(string $name, string $body, int $level) use ($container) : callable {
            return function() use ($name, $body, $level, $container) : bool {
                (new notification_repository($container->get(moodle_database::class)))->save(
                    new notification(
                        null, $name, $body, new DateTimeImmutable, $level, 'touchpounts'
                    ),
                    'quotas'
                );

                return true;
            };
        };
    },

    'touchpoints.callables.getRepository' => function($container) : callable {
        return function() use ($container) : touchpoint_repository {
            return $container->get(touchpoint_repository::class);
        };
    },

    'touchpoints.callables.getTouchpoints' => function($container) : callable {
        return function(touchpoint_repository $repository) : array {
            return $repository->get_touchpoints();
        };
    },

    'touchpoints.callables.filterRunnableTouchpoints' => function($container) : callable {
        return function(array $touchpoints) : array {
            return array_filter(
                $touchpoints,
                function(touchpoint $touchpoint) : bool {
                    return $touchpoint->can_execute_actions();
                }
            );
        };
    },

    // A function that filters out actions that have been disabled by the site admin.
    // Originally entire touchpoints were excluded by these settings, but it makes more
    // sense for the setting to control the actions, since, for example, the admin
    // may want to stop getting emails but keep getting the little red number, and both
    // of those things are tied to the same touchpoint.
    'touchpoints.callables.filterPermittedTouchpointActions' => function($container) : callable {
        return function(string $touchpointname, array $actions) : array {
            $enabled = function(string $touchpointname, string $value) : bool {
                return
                    !get_config('moodlecloudnotifications', 'touchpoints_' . touchpoint::name_to_identifier($touchpointname)) ||
                    strpos(
                        get_config(
                            'moodlecloudnotifications',
                            'touchpoints_' . touchpoint::name_to_identifier($touchpointname)
                        ),
                        $value
                    ) !== false;
            };

            return array_filter(
                $actions,
                function(stdClass $action) use ($touchpointname, $enabled) : bool {
                    if ($action->name == 'signup_touchpoint') {
                        return $enabled($touchpointname, 'email');
                    }

                    if ($action->name == 'admin_notification') {
                        return $enabled($touchpointname, 'sitenotifications');
                    }

                    return true;
                }
            );
        };
    },

    // A function that knows how to run a list of touchpoints and returns
    // a list of the results (each of which are transformed by the provided
    // $resulttransformer)
    'touchpoints.callables.executeTouchpoints' => function($container) : callable {
        return function(callable $resulttransformer) {
            return function(array $runnables) use ($resulttransformer) : array {
                return array_reduce($runnables, function(array $c, touchpoint $v) use ($resulttransformer) : array {
                    return array_merge($c, [$v->get_name() => $resulttransformer($v->execute())]);
                }, []);
            };
        };
    },

    // A function that knows how to persist touchpoints from a list of touchpoint
    // computation results.
    'touchpoints.callables.persistTouchpointsFromComputationResult' => function($container) {
        return function(array $results) use ($container) {
            return array_map(function(string $name, computation_result $result) use ($container) {
                return functions::bimap(
                    $container->get('touchpoints.callables.persistFailure')(
                        $container->get(touchpoint_repository::class)
                                  ->get_touchpoint_by_name($name)
                                  ->increment_attempts()
                    ),
                    $container->get('touchpoints.callables.persistSuccess')(
                        $container->get(touchpoint_repository::class)
                                  ->get_touchpoint_by_name($name)
                                  ->increment_attempts()
                    ),
                    $result
                );
            }, array_keys($results), $results);
        };
    },

    // This function takes a touchpoint, and returns a function that takes a list of actionresults.
    // It then uses the touchpoint repository to persist the touchpoint and actions in a failed stated
    // (either awaiting a retry, or complete failure).
    'touchpoints.callables.persistFailure' => function($container) {
        return function (touchpoint $touchpoint) use ($container) {
            return function(array $actionresults) use ($container, $touchpoint) {
                return $container->get(touchpoint_repository::class)->persist(
                    $touchpoint,
                    $touchpoint->get_num_attempts() >= $container->get('touchpoints.maxAttempts')
                                                     ? touchpoint::STATUS_FAILURE
                                                     : touchpoint::STATUS_RETRYING,
                    // This will be a plain array with the key being the action name, and the value being the raw
                    // failure information.
                    functions::failures($actionresults)
                );
            };
        };
    },

    // As above, but touchpoints are persisted in a successful state.
    'touchpoints.callables.persistSuccess' => function($container) {
        return function(touchpoint $touchpoint) use ($container) {
            return function(array $actionresults) use ($container, $touchpoint) {
                return $container->get(touchpoint_repository::class)->persist(
                    $touchpoint,
                    touchpoint::STATUS_SUCCESS
                );
            };
        };
    },

    // Queue the next touchpoints depending on whether they failed or not.
    // The outcome of this is a list of computation results where the wrapped
    // value is the ID of the next queued touchpoint (or -1 if no next touchpoint
    // is queued).
    'touchpoints.callables.queueNext' => function($container) {
        return function($persistedtouchpoints) use ($container) {
            return array_map(function($touchpointresult) use ($container) {
                return functions::bimap(
                    $container->get('touchpoints.callables.queueNextFailure'),
                    $container->get('touchpoints.callables.queueNextSuccess'),
                    $touchpointresult
                );
            }, $persistedtouchpoints);
        };
    },


    // If a touchpoint succeeded, we always queue the next one.
    'touchpoints.callables.queueNextSuccess' => function($container) {
        return function($touchpoint) use ($container) {
            return $container->get(moodle_database::class)->insert_record(
                'moodlecloud_touchpoints',
                (object)[
                    'created' => time(),
                    'name' => $touchpoint->get_name()
                ]
            );
        };
    },

    // In the failure case, we only queue the next touchpoint it max attempts have been exceeded.
    'touchpoints.callables.queueNextFailure' => function($container) {
        return function($touchpoint) use ($container) {
            if ($touchpoint->get_num_attempts() >= $container->get('touchpoints.maxAttempts')) {
                return $container->get('touchpoints.callables.queueNextSuccess')($touchpoint);
            }

            return -1;
        };
    }
];
