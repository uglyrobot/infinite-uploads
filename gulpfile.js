const gulp = require('gulp');
const wpPot = require('gulp-wp-pot');
const zip = require('gulp-zip');

gulp.task('pot', function () {
  return gulp.src(['**/*.php', '!node_modules/**', '!vendor/**'])
    .pipe(wpPot({
      domain: 'infinite-uploads',
      package: 'Infinite Uploads'
    }))
    .pipe(gulp.dest('infinite-uploads.pot'));
});

gulp.task('zip', gulp.series('pot', function () {
  return gulp.src([
    './**/*',
    '!node_modules/**',
    '!bin/**',
    '!tests/**',
    '!./gulpfile.js',
    '!./package.json',
    '!./package-lock.json',
    '!./phpunit.xml.dist',
    '!./.*'
  ])
    .pipe(zip('infinite-uploads.zip'))
    .pipe(gulp.dest('./../'));
}));

gulp.task('default', gulp.series('pot'));
