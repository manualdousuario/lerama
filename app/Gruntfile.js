const sass = require('sass');

module.exports = function (grunt) {

    grunt.initConfig({

        sass: {
            options: {
                implementation: sass,
                sourceMap: false,
                style: 'expanded'
            },
            dist: {
                files: {
                    '.tmp/custom.css': 'public/src/scss/custom.scss'
                }
            }
        },

        concat: {
            css: {
                options: {
                    process: function (src, filepath) {
                        if (filepath.indexOf('bootstrap-icons') !== -1) {
                            return src.replace(/url\(["']?(?:\.\/)?fonts\//g, 'url("/assets/fonts/');
                        }
                        return src;
                    }
                },
                src: [
                    'node_modules/bootstrap/dist/css/bootstrap.min.css',
                    'node_modules/bootstrap-icons/font/bootstrap-icons.min.css',
                    '.tmp/custom.css'
                ],
                dest: '.tmp/lerama.css'
            },
            layout: {
                src: ['public/src/js/theme.js', 'public/src/js/layout.js'],
                dest: '.tmp/layout.js'
            },
            home: {
                src: ['public/src/js/home.js'],
                dest: '.tmp/home.js'
            },
            'feed-builder': {
                src: ['public/src/js/feed-builder.js'],
                dest: '.tmp/feed-builder.js'
            },
            shuffle: {
                src: ['public/src/js/theme.js', 'public/src/js/shuffle.js'],
                dest: '.tmp/shuffle.js'
            },
            'suggest-feed': {
                src: ['public/src/js/suggest-feed.js'],
                dest: '.tmp/suggest-feed.js'
            }
        },

        cssmin: {
            dist: {
                files: {
                    'public/assets/css/lerama.min.css': '.tmp/lerama.css'
                }
            }
        },

        uglify: {
            options: {
                compress: true,
                mangle: true
            },
            dist: {
                files: {
                    'public/assets/js/layout.min.js':       '.tmp/layout.js',
                    'public/assets/js/home.min.js':         '.tmp/home.js',
                    'public/assets/js/feed-builder.min.js': '.tmp/feed-builder.js',
                    'public/assets/js/shuffle.min.js':      '.tmp/shuffle.js',
                    'public/assets/js/suggest-feed.min.js': '.tmp/suggest-feed.js'
                }
            }
        },

        copy: {
            lora: {
                files: [{
                    expand: true,
                    cwd:    'public/src/fonts/',
                    src:    ['**/*'],
                    dest:   'public/assets/fonts/'
                }]
            },
            bootstrapIconsFonts: {
                files: [{
                    expand: true,
                    cwd:    'node_modules/bootstrap-icons/font/fonts/',
                    src:    ['**/*'],
                    dest:   'public/assets/fonts/'
                }]
            }
        },

        clean: {
            tmp: ['.tmp'],
            assets: [
                'public/assets/css/!(*.min.css)',
                'public/assets/js/!(*.min.js)'
            ]
        },

        watch: {
            scss: {
                files: ['public/src/scss/**/*.scss'],
                tasks: ['sass', 'concat:css', 'cssmin']
            },
            js: {
                files: ['public/src/js/**/*.js'],
                tasks: ['concat:layout', 'concat:home', 'concat:feed-builder', 'concat:shuffle', 'concat:suggest-feed', 'uglify']
            }
        }

    });

    grunt.loadNpmTasks('grunt-sass');
    grunt.loadNpmTasks('grunt-contrib-concat');
    grunt.loadNpmTasks('grunt-contrib-cssmin');
    grunt.loadNpmTasks('grunt-contrib-uglify');
    grunt.loadNpmTasks('grunt-contrib-copy');
    grunt.loadNpmTasks('grunt-contrib-watch');
    grunt.loadNpmTasks('grunt-contrib-clean');

    grunt.registerTask('default', [
        'clean:assets',
        'copy',
        'sass',
        'concat',
        'cssmin',
        'uglify',
        'clean:tmp'
    ]);

};
