const mix = require('laravel-mix');
const fs = require("fs");

// Set the public path to the module's assets directory
mix.setPublicPath('modules/einvoice/assets');

// Compile the CodeMirror source file
mix.js('modules/einvoice/assets/codemirror.js', 'builds')
   // Compile the template file
   .js('modules/einvoice/assets/template.js', 'builds')
   // Process and minify CSS
   .postCss('modules/einvoice/assets/codemirror.css', 'builds', [
     require('cssnano')({ 
       preset: ['default', {
         discardComments: {
           removeAll: true,
         },
         normalizeWhitespace: true
       }]
     })
   ])
   // Add versioning
   .sourceMaps(false)
   .version();

// Disable notifications
mix.disableNotifications();

// Use webpack to resolve npm modules
mix.webpackConfig({
  resolve: {
    modules: ['node_modules']
  }
});


if (mix.inProduction()) {
  mix.after(() => {
    let migrationFile = fs.readFileSync("./application/config/migration.php");
    let versionRegex = /(\['migration_version'\] = )(\d+;) (\/\/) (\d.\d.\d)/gm;
    let versionConfig = versionRegex.exec(migrationFile)[4];
    console.log(versionConfig);
    [
      "modules/einvoice/assets/builds/codemirror.js",
      "modules/einvoice/assets/builds/template.js",
      "modules/einvoice/assets/builds/codemirror.css",
    ].forEach((headerableFile) => {
      const data = fs.readFileSync(headerableFile);
      const fd = fs.openSync(headerableFile, "w+");
      const insert = Buffer.from("/* " + versionConfig + " */ \n");
      fs.writeSync(fd, insert, 0, insert.length, 0);
      fs.writeSync(fd, data, 0, data.length, insert.length);
      fs.close(fd, (err) => {
        if (err) throw err;
      });
    });
  });
}
