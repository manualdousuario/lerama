const gulp = require('gulp');
const concat = require('gulp-concat');
const cleanCSS = require('gulp-clean-css');
const rename = require('gulp-rename');
const fs = require('fs');
const path = require('path');

const paths = {
  bootstrap: {
    css: 'node_modules/bootstrap/dist/css/bootstrap.min.css',
    cssMap: 'node_modules/bootstrap/dist/css/bootstrap.min.css.map'
  },
  bootstrapIcons: {
    css: 'node_modules/bootstrap-icons/font/bootstrap-icons.min.css',
    fonts: 'node_modules/bootstrap-icons/font/fonts/*'
  },
  dest: {
    css: 'public/assets/css',
    fonts: 'public/assets/css/fonts'
  }
};

function bootstrapCSS() {
  return gulp.src([paths.bootstrap.css, paths.bootstrap.cssMap])
    .pipe(gulp.dest(paths.dest.css));
}

function bootstrapIconsCSS() {
  return gulp.src(paths.bootstrapIcons.css)
    .pipe(gulp.dest(paths.dest.css));
}

function bootstrapIconsFonts() {
  return gulp.src(paths.bootstrapIcons.fonts, { encoding: false })
    .pipe(gulp.dest(paths.dest.fonts));
}

function combinedCSS() {
  return gulp.src([
    paths.bootstrap.css,
    paths.bootstrapIcons.css
  ])
    .pipe(concat('lerama.css'))
    .pipe(gulp.dest(paths.dest.css))
    .pipe(cleanCSS())
    .pipe(rename({ suffix: '.min' }))
    .pipe(gulp.dest(paths.dest.css));
}

function watchFiles() {
  gulp.watch('node_modules/bootstrap/dist/css/**/*', bootstrapCSS);
  gulp.watch('node_modules/bootstrap-icons/font/**/*', gulp.parallel(bootstrapIconsCSS, bootstrapIconsFonts));
}

exports.bootstrap = bootstrapCSS;
exports.icons = gulp.parallel(bootstrapIconsCSS, bootstrapIconsFonts);
exports.combined = combinedCSS;
exports.watch = watchFiles;

exports.default = gulp.series(
  gulp.parallel(bootstrapIconsFonts),
  combinedCSS
);