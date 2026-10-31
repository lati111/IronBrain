import {toast} from "../../../../main";
import {closeModal, openModal} from "../../../../components/modal";
import {roundAmount} from "./inventory";
import {MIN_DECIMAL_AMOUNT} from "./types";

/** The amount modal, used both for adding stock and for moving part of a stack */

let pendingAmountAction: ((amount: number) => Promise<void> | void) | null = null;
let pendingAmountMax: number | null = null;
let pendingAmountDecimals = false;

/**
 * Asks for an amount before performing an action with it
 * @param decimals Whether fractions such as 0.5 are allowed, as for ingredients. Equipment only comes in whole pieces
 */
export function openAmountModal(title: string, amount: number, max: number | null, action: (amount: number) => Promise<void> | void, decimals: boolean = false): void {
    pendingAmountAction = action;
    pendingAmountMax = max;
    pendingAmountDecimals = decimals;

    document.getElementById('amount-modal-title')!.textContent = title;
    const input = document.getElementById('amount-modal-input') as HTMLInputElement;
    setAmountStep(input, decimals);
    input.value = String(amount);
    input.max = max !== null ? String(max) : '';

    openModal('amount-modal');
    input.focus();
    input.select();
}

export async function confirmAmount(): Promise<void> {
    const amount = roundAmount(parseFloat((document.getElementById('amount-modal-input') as HTMLInputElement).value));
    const min = pendingAmountDecimals ? MIN_DECIMAL_AMOUNT : 1;
    const whole = pendingAmountDecimals || Number.isInteger(amount);

    if (isNaN(amount) || amount < min || !whole || (pendingAmountMax !== null && amount > pendingAmountMax)) {
        const kind = pendingAmountDecimals ? 'an amount' : 'a whole amount';
        toast(pendingAmountMax !== null ? `Enter ${kind} between ${min} and ${pendingAmountMax}` : `Enter ${kind} of at least ${min}`);
        return;
    }

    const action = pendingAmountAction;
    pendingAmountAction = null;
    closeModal('amount-modal');

    if (action !== null) {
        await action(amount);
    }
}

/** Lets an amount input take fractions of an ingredient, or only whole pieces of equipment and meals */
export function setAmountStep(input: HTMLInputElement, decimals: boolean): void {
    input.min = decimals ? String(MIN_DECIMAL_AMOUNT) : '1';
    input.step = input.min;
}
