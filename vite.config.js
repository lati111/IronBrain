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
                ...css_files,
                ...ts_files,
            ],
            refresh: true,
        }),
    ],
});
