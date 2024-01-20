export function toDisplayString(string:string):string {
    string = string.replace('_', ' ');
    return string;
}

export function toast(string:string) {
    console.log(string)
}
