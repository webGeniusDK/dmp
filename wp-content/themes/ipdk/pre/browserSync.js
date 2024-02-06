const browserSync = require('browser-sync').create();
const chokidar = require('chokidar');

browserSync.init({
	https: {
		key: '../webgenius.local+1-key.pem',
		cert: '../webgenius.local+1.pem',
	},
	proxy: 'http://dmp.local',
	port: 3535,
	injectChanges: true,
	watchEvents: ['change', 'add', 'unlink', 'addDir', 'unlinkDir'],
	files: ['../**/*.php'],
});

// Watch for PHP file changes
chokidar.watch('../**/*.php', { ignored: /node_modules/ }).on('change', (path) => {
	console.log(`PHP File ${path} has been changed`);
	browserSync.reload();
});
// Watch for Single JS file changes
chokidar.watch('../js/**/*.js', { ignored: /node_modules/ }).on('change', (path) => {
	console.log(`JS File ${path} has been changed`);
	browserSync.reload();
});

// Watch for compiled CSS file changes for injection
chokidar.watch(['../style.css', '../css/**/*.css'], { ignored: /node_modules/ }).on('change', (path) => {
	console.log(`CSS file ${path} has been changed`);
	browserSync.reload(path);
});

// Watch only for the final JS file changes
chokidar.watch('../js/custom.min.js').on('change', (path) => {
	console.log(`Final JS file ${path} has been changed`);
	browserSync.reload();
});
