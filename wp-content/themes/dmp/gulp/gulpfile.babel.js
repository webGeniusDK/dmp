/**
 * Gulpfile.
 *
 * Gulp with WordPress.
 *
 * Implements:
 *      1. Live reloads browser with BrowserSync.
 *      2. CSS: Sass to CSS conversion, error catching, Autoprefixing, Sourcemaps,
 *         CSS minification, and Merge Media Queries.
 *      3. JS: Concatenates & uglifies Vendor and Custom JS files.
 *      4. Images: Minifies PNG, JPEG, GIF and SVG images.
 *      5. Watches files for changes in CSS or JS.
 *      6. Watches files for changes in PHP.
 *      7. Corrects the line endings.
 *      8. InjectCSS instead of browser page reload.
 *      9. Generates .pot file for i18n and l10n.
 *
 * @tutorial https://github.com/ahmadawais/WPGulp
 * @author Ahmad Awais <https://twitter.com/MrAhmadAwais/>
 */

/**
 * Load WPGulp Configuration.
 *
 * TODO: Customize your project in the wpgulp.js file.
 */
const config = require( './wpgulp.config.js' );

/**
 * Load Plugins.
 *
 * Load gulp plugins and passing them semantic names.
 */
const gulp = require( 'gulp' ); // Gulp of-course.

// CSS related plugins.
const sass = require( 'gulp-sass' ); // Gulp plugin for Sass compilation.
const minifycss = require( 'gulp-uglifycss' ); // Minifies CSS files.
const autoprefixer = require( 'gulp-autoprefixer' ); // Autoprefixing magic.
const mmq = require( 'gulp-merge-media-queries' ); // Combine matching media queries into one.

// JS related plugins.
const concat = require( 'gulp-concat' ); // Concatenates JS files.
const uglify = require( 'gulp-uglify' ); // Minifies JS files.
const babel = require( 'gulp-babel' ); // Compiles ESNext to browser compatible JS.

// Image related plugins.
const imagemin = require( 'gulp-imagemin' ); // Minify PNG, JPEG, GIF and SVG images with imagemin.

// Utility related plugins.
const rename = require( 'gulp-rename' ); // Renames files E.g. style.css -> style.min.css.
const lineec = require( 'gulp-line-ending-corrector' ); // Consistent Line Endings for non UNIX systems. Gulp Plugin for Line Ending Corrector (A utility that makes sure your files have consistent line endings).
const filter = require( 'gulp-filter' ); // Enables you to work on a subset of the original files by filtering them using a glob.
const sourcemaps = require( 'gulp-sourcemaps' ); // Maps code in a compressed file (E.g. style.css) back to it’s original position in a source file (E.g. structure.scss, which was later combined with other css files to generate style.css).
const notify = require( 'gulp-notify' ); // Sends message notification to you.
const browserSync = require( 'browser-sync' ).create(); // Reloads browser and injects CSS. Time-saving synchronized browser testing.
const cache = require( 'gulp-cached' ); // Cache files in stream for later use.
const remember = require( 'gulp-remember' ); //  Adds all the files it has ever seen back into the stream.
const plumber = require( 'gulp-plumber' ); // Prevent pipe breaking caused by errors from gulp plugins.
const beep = require( 'beepbeep' );

/**
 * Custom Error Handler.
 *
 * @param Mixed err
 */
const errorHandler = r => {
	notify.onError( '\n\n❌  ===> ERROR: <%= error.message %>\n' )( r );

	//beep();

	// this.emit('end' );
};

/**
 * Task: `browser-sync`.
 *
 * Live Reloads, CSS injections, Localhost tunneling.
 * @link http://www.browsersync.io/docs/options/
 *
 * @param {Mixed} done Done.
 */
const browsersync = done => {
	browserSync.init({
		port: 3534,
		proxy: config.projectURL,
		open: config.browserAutoOpen,
		injectChanges: config.injectChanges,
		watchEvents: [ 'change', 'add', 'unlink', 'addDir', 'unlinkDir' ]
	});
	done();
};

// Helper function to allow browser reload with Gulp 4.
const reload = done => {
	browserSync.reload( true );
	done();
};

gulp.task( 'styles', () => {
	return gulp
		.src( config.styleSRC, {allowEmpty: true})
		.pipe( plumber( errorHandler ) )
		.pipe( sourcemaps.init() )
		.pipe(
			sass({
				errLogToConsole: config.errLogToConsole,
				outputStyle: config.outputStyle,
				precision: config.precision
			})
		)
		.on( 'error', sass.logError )
		.pipe( sourcemaps.write({includeContent: false}) )
		.pipe( sourcemaps.init({loadMaps: true}) )
		.pipe( autoprefixer( config.BROWSERS_LIST ) )
		.pipe( sourcemaps.write( './' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( mmq({log: true}) ) // Merge Media Queries only for .min.css version.
		.pipe( browserSync.stream() ) // Reloads style.css if that is enqueued.
		.pipe( rename({suffix: '.min'}) )
		.pipe( minifycss({maxLineLen: 10}) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.styleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( browserSync.stream() ) // Reloads style.min.css if that is enqueued.
		.pipe(
			notify({
				message: '\n\n✅  ===> STYLES — completed!\n',
				onLast: true
			})
		);
});
gulp.task( 'minStyles', () => {
	return gulp
		.src( config.styleSRC, { allowEmpty: true })
		.pipe( plumber( errorHandler ) )
		.pipe( sourcemaps.init() )
		.pipe(
			sass({
				errLogToConsole: config.errLogToConsole,
				outputStyle: config.outputStyle,
				precision: config.precision
			})
		)
		.on( 'error', sass.logError )
		.pipe( sourcemaps.write({ includeContent: true}) )
		.pipe( sourcemaps.init({ loadMaps: true }) )
		.pipe( autoprefixer( config.BROWSERS_LIST ) )
		.pipe( sourcemaps.write( './' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.styleDestination ) )
		.pipe( browserSync.stream() ) // Reloads style.min.css if that is enqueued.
		.pipe( notify({ message: '\n\n✅  ===> STYLES — completed!\n', onLast: true }) );
});
gulp.task( 'partialStyles', () => {
	return gulp
		.src([ config.pageStyleSRC, config.componentStyleSRC ], { allowEmpty: true })
		.pipe( notify({ message: '\n\n✅  ===> STYLES — One!\n', onLast: true }) )
		.pipe( cache( 'biofolic-styling' ) )
		.pipe( plumber( errorHandler ) )
		.pipe( sourcemaps.init() )
		.pipe(
			sass({
				errLogToConsole: config.errLogToConsole,
				outputStyle: config.outputStyle,
				precision: config.precision
			})
		)
		.pipe( notify({ message: '\n\n✅  ===> STYLES — two!\n', onLast: true }) )
		.on( 'error', sass.logError )
		.pipe( sourcemaps.write({ includeContent: true}) )
		.pipe( sourcemaps.init({ loadMaps: true }) )
		.pipe( autoprefixer( config.BROWSERS_LIST ) )
		.pipe( sourcemaps.write( './' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.partialStyleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( mmq({ log: true }) ) // Merge Media Queries only for .min.css version.
		.pipe( browserSync.stream() ) // Reloads style.css if that is enqueued.
		.pipe( rename({ suffix: '.min' }) )
		.pipe( minifycss({ maxLineLen: 10 }) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.partialStyleDestination ) )
		.pipe( filter( '**/*.css' ) ) // Filtering stream to only css files.
		.pipe( browserSync.stream() ) // Reloads style.min.css if that is enqueued.
		.pipe( notify({ message: '\n\n✅  ===> STYLES — completed!\n', onLast: true }) );

});

//Grab js files in assets/vendor and minify them and copy to js folder
gulp.task( 'vendorsJS', () => {
	return gulp
		.src( config.jsVendorSRC, {since: gulp.lastRun( 'vendorsJS' )}) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe( babel({
			presets: [
				[
					'@babel/preset-env', // Preset to compile your modern JS to ES5.
					{
						targets: {browsers: config.BROWSERS_LIST} // Target browser list to support.
					}
				]
			]
		}) )
		.pipe( remember( config.jsVendorSRC ) ) // Bring all files back to stream.
		.pipe( rename({ suffix: '.min' }) )
		.pipe( uglify({
			mangle: false
		}) )
		.pipe( lineec() )
		.pipe( gulp.dest( config.jsVendorDestination ) )
		.pipe( notify({ message: '\n\n✅  ===> VENDOR JS — completed!\n', onLast: true })
		);
});

gulp.task( 'customJS', () => {
	return gulp
		.src( config.jsCustomSRC, {since: gulp.lastRun( 'customJS' )}) // Only run on changed files.
		.pipe( plumber( errorHandler ) )
		.pipe(
			babel({
				presets: [
					[
						'@babel/preset-env', // Preset to compile your modern JS to ES5.
						{
							targets: {browsers: config.BROWSERS_LIST} // Target browser list to support.
						}
					]
				]
			})
		)
		.pipe( remember( config.jsCustomSRC ) ) // Bring all files back to stream.
		.pipe( concat( config.jsCustomFile + '.js' ) )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsCustomDestination ) )
		.pipe(
			rename({
				basename: config.jsCustomFile,
				suffix: '.min'
			})
		)
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsCustomDestination ) )
		.pipe(
			notify({
				message: '\n\n✅  ===> CUSTOM JS — completed!\n',
				onLast: true
			})
		);
});


gulp.task( 'vendorJsMin', function( done ) {
	gulp.src( config.jsVendorsMinSrc )
		.pipe( rename({ suffix: '.min' }) )
		.pipe( uglify() )
		.pipe( lineec() ) // Consistent Line Endings for non UNIX systems.
		.pipe( gulp.dest( config.jsVendorDestination ) )
		.pipe( notify({message: 'TASK: "vendorsJs" Completed! 💯', onLast: true}) );
	done();
});

gulp.task( 'images', () => {
	return gulp
		.src( config.imgSRC )
		.pipe(
			cache(
				imagemin([
					imagemin.gifsicle({interlaced: true}),
					imagemin.mozjpeg({quality: 60, progressive: true}),
					imagemin.optipng({optimizationLevel: 3}), // 0-7 low-high.
					imagemin.svgo({
						plugins: [ {removeViewBox: true}, {cleanupIDs: false} ]
					})
				])
			)
		)
		.pipe( gulp.dest( config.imgDST ) )
		.pipe(
			notify({
				message: '\n\n✅  ===> IMAGES — completed!\n',
				onLast: true
			})
		);
});
gulp.task( 'clearCache', function( done ) {
	return cache.clearAll( done );
});

/**
 * Watch Tasks.
 * gulp.watch( config.watchJsVendor, gulp.series( 'vendorsJS', reload ) ); // Reload on vendorsJS file changes.
 * Watches for file changes and runs specific tasks.
 */
gulp.task(
	'default',
	gulp.parallel( '' +
		'styles', 'partialStyles', 'customJS', 'vendorJsMin', browsersync, () => {
		gulp.watch( config.watchPhp, reload ); // Reload on PHP file changes.
		gulp.watch( config.watchStyles, gulp.parallel( 'styles' ) ); // Reload on SCSS file changes.
		gulp.watch( config.watchPartialStyles, gulp.parallel( 'partialStyles' ) );
		gulp.watch( config.jsVendorsMinSrc, gulp.series( 'vendorJsMin', reload ) );
		gulp.watch( config.watchJsCustom, gulp.series( 'customJS', reload ) ); // Reload on customJS file changes.
	})
);
