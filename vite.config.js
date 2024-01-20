import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { readdirSync, statSync } from 'fs';
const path = require("path")

const getAllFiles = function(dirPath, arrayOfFiles) {
    let files = readdirSync(dirPath)

    arrayOfFiles = arrayOfFiles || []

    files.forEach(function(file) {
        if (statSync(dirPath + "/" + file).isDirectory()) {
            arrayOfFiles = getAllFiles(dirPath + "/" + file, arrayOfFiles)
        } else {
            arrayOfFiles.push(path.join(__dirname, dirPath, "/", file))
        }
    })

    return arrayOfFiles
}

const css_files = getAllFiles("./resources/css/")
const ts_files = getAllFiles("./resources/ts/")

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                ...css_files,
                ...ts_files,
                'resources/ts/app.ts',
                // 'resources/css/components/datalist/cardlist.css',
                // 'resources/css/components/form/components/file_uploader.css',
                // 'resources/css/components/form/components/image_uploader.css',
                //
                // 'resources/project/pksanc/box.css',
                //
                // 'resources/ts/app.ts',
                // 'resources/ts/ajax.ts',
                // 'resources/ts/main.ts',
                // 'resources/ts/bootstrap.ts',
                // 'resources/ts/utilities.ts',
                // 'resources/ts/components/dataproviders/filterlist.ts',
                // 'resources/ts/components/form/image_uploader.ts',
                // 'resources/ts/components/form/permission_select.ts',
                // 'resources/ts/components/baseDataprovider.ts',
                // 'resources/ts/components/cardlist.ts',
                // 'resources/ts/components/datatable.ts',
                // 'resources/ts/components/modal.ts',
                // 'resources/ts/datatable/permission_toggle.ts',


            ],
            refresh: true,
        }),
    ],
});
