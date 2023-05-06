import { postData } from "../ajax";

async function togglePermission(hasPermission:Boolean, url:string) {
    const formData = new FormData();
    if (hasPermission === true) {
        formData.append('hasPermission', "1");
    } else {
        formData.append('hasPermission', "0");
    }

    await postData(url, formData);
}

(<any>window).togglePermission = togglePermission;
