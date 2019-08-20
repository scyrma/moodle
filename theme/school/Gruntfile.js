'use strict';

module.exports = function(grunt) {

    // Import modules.
    var path = require("path");

    var PWD = process.cwd();

    grunt.initConfig({
        exec: {
            postcss: {
                command: 'npm run postcss'
            }
        },
        watch: {
            // Watch for any changes to less files and compile.
            files: ["scss/**/*.scss"],
            tasks: ["compile"],
            options: {
                spawn: false,
                livereload: true
            }
        },
        stylelint: {
            scss: {
                options: {
                    syntax: "scss"
                },
                src: ["scss/**/*.scss"]
            },
            css: {
                src: ["*/**/*.css"],
                options: {
                    configOverrides: {
                        rules: {
                            // These rules have to be disabled in .stylelintrc for scss compat.
                            "at-rule-no-unknown": true,
                            "no-browser-hacks": [true, {"severity": "warning"}]
                        }
                    }
                }
            }
        },
        jshint: {
            options: {
                jshintrc: true
            },
            files: ["**/amd/src/*.js"]
        },
        uglify: {
            dynamic_mappings: {
                files: grunt.file.expandMapping(
                    ["**/src/*.js", "!**/node_modules/**"],
                    "",
                    {
                        cwd: PWD,
                        rename: function(destBase, destPath) {
                            destPath = destPath.replace("src", "build");
                            destPath = destPath.replace(".js", ".min.js");
                            destPath = path.resolve(PWD, destPath);
                            return destPath;
                        }
                    }
                )
            }
        },
        sass: {
            options: {
                style: 'expanded'
            },
            dist: {
                files: {
                    'style/school.css': 'scss/gruntcompile.scss'
                }
            }
        },
    });

    // Load contrib tasks.
    grunt.loadNpmTasks("grunt-contrib-watch");
    grunt.loadNpmTasks("grunt-exec");

    // Load core tasks.
    grunt.loadNpmTasks("grunt-contrib-uglify");
    grunt.loadNpmTasks("grunt-contrib-jshint");
    grunt.loadNpmTasks("grunt-sass");
    grunt.loadNpmTasks("grunt-stylelint");

    // Register CSS taks.
    grunt.registerTask("css", ["stylelint:scss", "stylelint:css"]);

    // Register tasks.
    grunt.registerTask("default", ["watch"]);

    grunt.registerTask("compile", [
        "sass",
        "exec:postcss"
    ]);

    grunt.registerTask("amd", ["jshint", "uglify"]);
};
