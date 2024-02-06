const sass = require('sass');
const path = require('path');
const fs = require('fs');
const cssnano = require('cssnano');
const postcss = require('postcss');

// Function to compile and minify SCSS file
async function compileAndMinifySCSS(inputPath, outputPath) {
	try {
		console.log(`Compiling: ${inputPath}`);
		console.log(`Outputting to: ${outputPath}`);

		const result = sass.renderSync({ file: inputPath });
		const minified = await postcss([cssnano]).process(result.css.toString(), { from: undefined });
		minified.warnings().forEach(warn => console.warn(warn.toString()));

		fs.mkdirSync(path.dirname(outputPath), { recursive: true });
		fs.writeFileSync(outputPath, minified.css);
		console.log(`Compiled and minified ${inputPath} to ${outputPath}`);
	} catch (error) {
		console.error(`Error compiling/minifying ${inputPath}:`, error);
	}
}

// Get the SCSS file that triggered the watch
const changedFile = process.argv[2];

console.log(`Received changed file: ${changedFile}`);

// Ensure changed file is provided and is a SCSS file
if (!changedFile || !changedFile.endsWith('.scss')) {
	console.error('No SCSS file specified or invalid file format.');
	process.exit(1);
}

// Define input and output paths
const inputPath = path.resolve(changedFile);
const relativeDir = path.relative(path.join(__dirname, '..', 'assets', 'partial-styles'), path.dirname(inputPath));
const outputFileName = path.basename(changedFile, '.scss') + '.min.css';
const outputPath = path.join(__dirname, '..', 'css', relativeDir, outputFileName);

// Compile and minify the SCSS file
compileAndMinifySCSS(inputPath, outputPath);
