/** The service state as the server sends it, shared by the kitchen and the floor */

export type MealStatus = 'planned' | 'prepped' | 'cooking' | 'done' | 'serving';

interface Clock {
    date: string;
    time: string;
    opening_time: string;
    closing_time: string;
    /** What the orders placed during the current service are worth */
    shift_total: number;
}

/** An ingredient, with how much is in the storage spaces and how much in the pile brought in during service */
export interface ServiceIngredient {
    uuid: string;
    name: string;
    icon: string | null;
    stored: number;
    pile: number;
}

export interface KitchenRecipe {
    uuid: string;
    /** The base recipe this is a variant of, which it is listed under */
    parent_uuid: string | null;
    name: string;
    /** What kind of recipe it is, such as a meal or drink */
    type: string;
    category: string | null;
    /** What the recipe is ordered at: its sale price while on sale */
    price: number;
    /** The usual price of the recipe */
    base_price: number;
    on_sale: boolean;
    prep_time: number;
    cooking_time: number;
    /** Ingredients taken by category, of which the one to use is chosen when planning the meal */
    categories: KitchenRecipeCategory[];
    /** Ingredients taken outright, of which a variant can be used instead */
    ingredients: KitchenRecipeIngredient[];
    /** Equipment other than cookers, of which a variant can be used instead */
    equipment: KitchenRecipeEquipment[];
}

export interface KitchenRecipeEquipment {
    equipment_uuid: string;
    stage: 'prep' | 'cooking' | 'serving';
    amount: number;
    /** The equipment itself first, then its variants, with how many of each are clean */
    options: {uuid: string, name: string, clean: number}[];
}

interface KitchenRecipeCategory {
    category: string;
    amount: number;
    /** The ingredients of the category, with how much of each is in storage and the pile together */
    options: KitchenIngredientOption[];
}

interface KitchenRecipeIngredient {
    ingredient_uuid: string;
    amount: number;
    /** The ingredient itself first, then its variants */
    options: KitchenIngredientOption[];
}

export interface KitchenIngredientOption {
    uuid: string;
    name: string;
    /** How much is in storage and the pile together */
    available: number;
    quality: number;
}

interface Cooker {
    uuid: string;
    name: string;
    /** How many pieces of the equipment there are, each holding one meal */
    slots: number;
}

export interface KitchenEquipment {
    uuid: string;
    name: string;
    category_label: string;
    /** In minutes, for each piece */
    cleaning_time: number;
    /** How many pieces are owned or stored in total */
    amount: number;
    in_use: number;
    dirty: number;
    clean: number;
}

export interface Meal {
    uuid: string;
    recipe_uuid: string;
    name: string;
    /** The category of the meal's recipe, by which other recipes can require it */
    recipe_category: string | null;
    status: MealStatus;
    prep_time: number;
    cooker_uuid: string | null;
    cooker_slot: number | null;
    cooking_minutes_left: number | null;
    /** How far along cooking is, from 0 to 1, or null when not cooking */
    cooking_progress: number | null;
    expires_in_minutes: number | null;
    spoiled: boolean;
    portions_left: number;
    /** The quality the meal is served at: how well it turned out, asked for once it is finished, with boosts added */
    quality: number | null;
    /** What boosts added to the quality */
    quality_bonus: number;
    /** The average quality of the ingredients it was made with, also added to the quality */
    ingredient_quality: number;
    /** How fast it is being worked on while prepped or cooking, where 1 is normal */
    speed: number | null;
    /** Whether the meal is done cooking or ready to serve */
    finished: boolean;
    /** Other meals still to be put in before it can be cooked */
    missing_meals: MissingMeal[];
}

/** Another meal a meal still waits on: of a specific recipe, or of any recipe of a category */
export interface MissingMeal {
    recipe_uuid: string | null;
    category: string | null;
    name: string;
    portions: number;
}

/** A plated portion waiting to be brought to a table */
export interface Plate {
    uuid: string;
    recipe_uuid: string;
    name: string;
    /** The quality of the meal it was plated from */
    quality: number | null;
    /** Minutes until the portion spoils, as the meal would in the serving area */
    expires_in_minutes: number | null;
    spoiled: boolean;
}

export interface OrderItem {
    uuid: string;
    recipe_uuid: string | null;
    name: string;
    customer_uuid: string | null;
    customer_name: string | null;
    price: number;
    served: boolean;
    /** Whether a plate was set aside for it, which the waiter is bringing */
    on_the_way: boolean;
    consumed: boolean;
    /** How satisfied the customer was, from 1 to 5, once served */
    satisfaction: number | null;
}

/** An order that is still being served or eaten */
export interface Order {
    uuid: string;
    table_uuid: string | null;
    /** Taking while the waiter takes it, then open until everything is served */
    status: 'taking' | 'open' | 'fulfilled';
    /** What the items are worth together */
    total_price: number;
    tip: number;
    discount: number;
    /** What the table pays once the order is finished: the items, less the discount, plus the tip */
    payable: number;
    ordered_at: string;
    ordered_minutes_ago: number;
    fulfilled_minutes_ago: number | null;
    items: OrderItem[];
    /** How satisfied the table is with what has been served so far, or null before anything was */
    satisfaction: number | null;
}

/** The work areas employees can work in */
export type WorkStationCode = 'prep' | 'cleaning';

export interface Employee {
    uuid: string;
    name: string;
    icon: string | null;
    /** The work area the employee works in, or null while idle or waiting tables */
    station: WorkStationCode | null;
    /** The uuids of the tables the employee waits, when waiting tables rather than working in a work area */
    tables: string[];
    /** The task the employee is on, such as "Prepping Stew" */
    task: string;
    /** The player controlling the employee, if any */
    player_uuid: string | null;
    player_name: string | null;
}

/** Who is looking at the service. Players only control the employees assigned to them */
interface Viewer {
    user_uuid: string | null;
    player: boolean;
}

/** A job queued in a work area: prepping a meal, or cleaning pieces of equipment */
export interface WorkJob {
    uuid: string;
    meal_uuid: string | null;
    equipment_uuid: string | null;
    /** A conversion of an ingredient done in the prep area */
    conversion: ConversionJob | null;
    amount: number;
    /** How many minutes the job takes at normal speed */
    work: number;
    /** How far along the job is, from 0 to 1 */
    progress: number;
    /** Minutes until the job is done, counting the jobs before it, or null while nobody works in the area */
    minutes_left: number | null;
}

interface ConversionJob {
    input_name: string;
    input_amount: number;
    output_name: string;
    output_amount: number;
}

/** A boost that can be activated during service, as often as wanted */
export interface ServiceBoost {
    uuid: string;
    name: string;
    target: 'equipment' | 'meal' | 'area';
    speed_bonus: number;
    minutes_off: number;
    quality_bonus: number;
    duration: number;
}

/** A boost active now, with what it is on and how long it has left */
interface ActiveBoost {
    uuid: string;
    name: string;
    speed_bonus: number;
    equipment_uuid: string | null;
    /** The single cooker of the equipment it is on, or null when on all of it */
    cooker_slot: number | null;
    meal_uuid: string | null;
    area: string | null;
    target_name: string;
    minutes_left: number;
}

/** A conversion of an ingredient into another, done in the prep area during service */
export interface ServiceConversion {
    uuid: string;
    input_name: string;
    output_name: string;
    /** How much of the output each piece of the input becomes */
    output_amount: number;
    /** How many minutes converting a piece takes at normal speed */
    minutes: number;
    /** How much of the input is stored and in the pile */
    available: number;
}

interface WorkStationState {
    /** The combined speed of the employees working there */
    speed: number;
    /** How far along the whole queue is, from 0 to 1, or null while there is nothing to do */
    progress: number | null;
    jobs: WorkJob[];
}

export interface ServiceState {
    clock: Clock;
    ingredients: ServiceIngredient[];
    conversions: ServiceConversion[];
    boosts: ServiceBoost[];
    active_boosts: ActiveBoost[];
    recipes: KitchenRecipe[];
    cookers: Cooker[];
    equipment: KitchenEquipment[];
    meals: Meal[];
    plates: Plate[];
    employees: Employee[];
    stations: Record<WorkStationCode, WorkStationState>;
    /** The jobs of the waiters: taking orders and bringing plates */
    waiting: WaitingJob[];
    orders: Order[];
    viewer: Viewer;
    /** How many changes the restaurant has seen, sent along when refreshing to learn whether anything changed */
    version: number;
}

/** A job of a table's waiter: taking an order there, or bringing a plated portion */
export interface WaitingJob {
    uuid: string;
    table_uuid: string;
    order_uuid: string | null;
    served_meal_uuid: string | null;
    /** Such as "Taking an order at Booth" or "Bringing Stew to Booth" */
    task: string;
    /** How far along the job is, from 0 to 1 */
    progress: number;
    /** Minutes until the job is done, counting the waiter's jobs before it, or null while nobody waits the table */
    minutes_left: number | null;
}
