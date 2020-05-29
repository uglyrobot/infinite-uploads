var gulp = require('gulp');
var wpPot = require('gulp-wp-pot');

gulp.task('default', function () {
  return gulp.src(['**/*.php', '!node_modules/**', '!vendor/**'])
    .pipe(wpPot({
      domain: 'iup',
      package: 'Infinite Uploads'
    }))
    .pipe(gulp.dest('infinite-uploads.pot'));
});
