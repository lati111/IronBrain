import {toast} from "../main";

export class IronbrainError extends Error {
    public constructor(message:string) {
        super(message);
        toast('An error occurred.');
    }
}
