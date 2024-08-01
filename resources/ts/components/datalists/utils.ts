/**
 * The setter for checkboxes within a datatable
 * @param cell The cell containing the checkbox
 * @param value The value in string format
 */
export function checkboxSetter(cell: HTMLTableCellElement, value: string) {
    let checkbox = cell.querySelector('input[type=checkbox]') as HTMLInputElement|null;
    if (checkbox === null) {
        checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        cell.append(checkbox)
    }

    if (value == '1') {
        checkbox.checked = true;
    }

    return cell;
}
