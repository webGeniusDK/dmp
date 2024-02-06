const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Define paths
const customJSDir = path.join(__dirname, '..', 'assets', 'js', 'custom');
const outputDir = path.join(__dirname, '..', 'js');
const customJSOutput = path.join(outputDir, 'custom.js');
const customJSMinOutput = path.join(outputDir, 'custom.min.js');

// Flag to indicate if a build is in progress
let isBuilding = false;

// Function to get all JS files from custom JS directory
function getJSFiles(dir) {
	return fs.readdirSync(dir)
		.filter(file => file.endsWith('.js'))
		.map(file => path.join(dir, file));
}

// Function to concatenate or copy JS files
function concatenateJS(files, output) {
	if (files.length > 1) {
		// Concatenate multiple files
		const filesArg = files.map(file => `"${file}"`).join(' ');
		execSync(`npx concat-cli -f ${filesArg} -o "${output}"`);
	} else if (files.length === 1) {
		// Copy single file
		fs.copyFileSync(files[0], output);
	} else {
		console.error('No JS files found in custom JS directory.');
		process.exit(1);
	}
}

// Function to transpile and minify JS
function processJS(input, output) {
	// Transpile JS file
	execSync(`npx babel "${input}" --out-file "${input}"`);

	// Minify JS file
	execSync(`npx terser "${input}" -o "${output}"`);
}

try {
	if (!isBuilding) {
		isBuilding = true;
		const jsFiles = getJSFiles(customJSDir);
		concatenateJS(jsFiles, customJSOutput);
		processJS(customJSOutput, customJSMinOutput);
		console.log('Custom JS processing completed.');
		isBuilding = false;
	}
} catch (error) {
	console.error('Error during custom JS processing:', error);
	isBuilding = false;
	process.exit(1);
}
