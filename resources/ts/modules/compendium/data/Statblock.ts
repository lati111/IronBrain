import {Resistance} from "./Resistance";

export class Statblock {
    /** @property Array The statblock's resistances */
    public resistances;
    /** @property Array The raw resistance data resistances */
    protected resistanceData: Array<Resistance>;

    public constructor(data) {
        console.log(data)

        this.importResistances(data['resistances'])
    }

    /**
     * Import the given resistance array into the statblock
     * @param {Array<Resistance>} resistances The resistances to import
     */
    protected importResistances(resistances: Array<Resistance>) {
        this.resistanceData = resistances
        this.resistances = {
            'BLUNT': 0,
            'SLASHING': 0,
            'PIERCING': 0,
            'FIRE': 0,
            'COLD': 0,
            'POISON': 0,
            'ACID': 0,
            'LIGHTNING': 0,
            'THUNDER': 0,
            'RADIANT': 0,
            'NECROTIC': 0,
            'PSYCHIC': 0,
        }

        for (const resistance of resistances) {
            if (resistance['is_base']) {
                if (resistance.stage > 0 && resistance.stage > this.resistances[resistance.element]) {
                    this.resistances[resistance.element] = resistance.stage;
                } else if (resistance.stage < 0) {
                    const newVal = this.resistances[resistance.element] + resistance.stage
                    this.resistances[resistance.element] = (newVal < -3) ? -3 : newVal;
                }
            }
        }
    }
}
