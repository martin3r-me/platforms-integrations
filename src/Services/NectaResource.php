<?php

namespace Platform\Integrations\Services;

/**
 * Registry aller necta.one Raw-API-Ressourcen (auto-generiert aus der OpenAPI-Spec).
 *
 * Die Raw-API ist READ-ONLY und vollständig einheitlich aufgebaut:
 *   GET /rawapi/{resource}?pageNumber=<n>&pageSize=<m>&<filter...>
 *
 * Es gibt 417 Ressourcen und KEINE Detail-/ID-Routen — jede Ressource ist eine
 * paginierte Liste. Diese Klasse ist die Single-Source-of-Truth für gültige
 * Ressourcen-Slugs und deren dokumentierte Query-Filter.
 *
 * @see \Platform\Integrations\Services\NectaApiService::list()
 */
final class NectaResource
{
    /** Buchhaltungsschnittstelle */
    public const ACCOUNTING_SYSTEMS_INTERFACES = "accounting-systems-interfaces";
    /** Rückstellung */
    public const ACCRUALS = "accruals";
    /** Behörde */
    public const AGENCYS = "agencys";
    /** Zuordnungen */
    public const ASSIGNMENTS = "assignments";
    /** Warengruppenindex */
    public const ASSORTMENT_INDEXS = "assortment-indexs";
    /** Warengruppentexte */
    public const ASSORTMENT_TEXTS = "assortment-texts";
    /** Warengruppen */
    public const ASSORTMENTS = "assortments";
    /** Chargennummerkorrektur */
    public const BATCH_NUMBER_CORRECTIONS = "batch-number-corrections";
    /** Bandpostengruppe Texte */
    public const BELT_POST_GROUP_TEXTS = "belt-post-group-texts";
    /** Bandpostengruppe */
    public const BELT_POST_GROUPS = "belt-post-groups";
    /** Bandposten */
    public const BELT_POSTS = "belt-posts";
    /** Aufgabe eines Geschäftsprozesses */
    public const BUSINESS_EVENTS = "business-events";
    /** Kalender Struktur Texte */
    public const CALENDAR_STRUCTURE_TEXTS = "calendar-structure-texts";
    /** Kalender Struktur */
    public const CALENDAR_STRUCTURES = "calendar-structures";
    /** Kalender Texte */
    public const CALENDAR_TEXTS = "calendar-texts";
    /** Kalender */
    public const CALENDARS = "calendars";
    /** Kassenwarengruppe Texte */
    public const CASH_REGISTER_GROUP_TEXTS = "cash-register-group-texts";
    /** Kassenwarengruppe */
    public const CASH_REGISTER_GROUPS = "cash-register-groups";
    /** Kassenbericht Kategorie Texte */
    public const CASH_REGISTER_REPORT_CATEGORY_TEXTS = "cash-register-report-category-texts";
    /** Kassenbericht Kategorie */
    public const CASH_REGISTER_REPORT_CATEGORYS = "cash-register-report-categorys";
    /** Kassenbericht Details */
    public const CASH_REGISTER_REPORT_DETAILS = "cash-register-report-details";
    /** Kassenbericht Gutscheine */
    public const CASH_REGISTER_REPORT_VOUCHERS = "cash-register-report-vouchers";
    /** Kassenbericht */
    public const CASH_REGISTER_REPORTS = "cash-register-reports";
    /** Kassensystemschnittstelle */
    public const CASHIER_INTERFACES = "cashier-interfaces";
    /** Kassensystem Artikel */
    public const CASHIER_ITEMS = "cashier-items";
    /** Verpflegsklassen */
    public const CATERING_CLASS = "catering-class";
    /** Verpflegsbudget Struktur */
    public const CATERING_CLASS_BUDGET_STRUCTURES = "catering-class-budget-structures";
    /** Verpflegsbudget */
    public const CATERING_CLASS_BUDGETS = "catering-class-budgets";
    /** Verpflegsklassen Texte */
    public const CATERING_CLASS_TEXTS = "catering-class-texts";
    /** Verpflegsstatistik */
    public const CATERING_STATISTICS = "catering-statistics";
    /** Änderungshistorie der Verpflegsstandsmeldungen */
    public const CATERING_STATISTICS_HISTORYS = "catering-statistics-historys";
    /** Verpflegsstatistik Struktur */
    public const CATERING_STATISTICS_STRUCTURES = "catering-statistics-structures";
    /** Verpflegsart Texte */
    public const CATERING_TYPE_TEXTS = "catering-type-texts";
    /** Verpflegsart */
    public const CATERING_TYPES = "catering-types";
    /** Zertifikat Texte */
    public const CERTIFICATE_TEXTS = "certificate-texts";
    /** Zertifikat */
    public const CERTIFICATES = "certificates";
    /** Klassifizierung Texte */
    public const CLASSIFICATION_TEXTS = "classification-texts";
    /** Klassifizierung */
    public const CLASSIFICATIONS = "classifications";
    /** Reklamationsklasse */
    public const COMPLAINT_CLASS = "complaint-class";
    /** Reklamationsklasse Bilder */
    public const COMPLAINT_CLASS_PICTURES = "complaint-class-pictures";
    /** Reklamationsklasse Texte */
    public const COMPLAINT_CLASS_TEXTS = "complaint-class-texts";
    /** Feedback/Reklamationen */
    public const COMPLAINTS = "complaints";
    /** Transportbox Texte */
    public const CONTAINER_BOX_TEXTS = "container-box-texts";
    /** Transportbox */
    public const CONTAINER_BOXS = "container-boxs";
    /** Behälter Texte */
    public const CONTAINER_TEXTS = "container-texts";
    /** Behälter */
    public const CONTAINERS = "containers";
    /** Objektklasse */
    public const COST_CENTER_CLASS = "cost-center-class";
    /** Objektklasse Texte */
    public const COST_CENTER_CLASS_TEXTS = "cost-center-class-texts";
    /** Kostenstellenerweiterungen */
    public const COST_CENTER_EXTENSTIONS = "cost-center-extenstions";
    /** Kostenstellenverknüfpungen */
    public const COST_CENTER_LINKS = "cost-center-links";
    /** Kostenstellen Texte */
    public const COST_CENTER_TEXTS = "cost-center-texts";
    /** Kostenstelle */
    public const COST_CENTERS = "cost-centers";
    /** Land Texte */
    public const COUNTRY_TEXTS = "country-texts";
    /** Land */
    public const COUNTRYS = "countrys";
    /** Währung */
    public const CURRENCYS = "currencys";
    /** Kunde Konto */
    public const CUSTOMER_ACCOUNTS = "customer-accounts";
    /** Kundenbonifiaktion */
    public const CUSTOMER_BONUS = "customer-bonus";
    /** Kundenkontakt Allergene */
    public const CUSTOMER_CONTACT_ALLERGENS = "customer-contact-allergens";
    /** Kundenkontakt Verpflegstage */
    public const CUSTOMER_CONTACT_CATERING_DAYS = "customer-contact-catering-days";
    /** Kundenkontakt Diätologieprofile */
    public const CUSTOMER_CONTACT_DIET_PROFILES = "customer-contact-diet-profiles";
    /** Kundenkontakt Historie (für künftige Entwicklung) */
    public const CUSTOMER_CONTACT_HISTORYS = "customer-contact-historys";
    /** Kundenkontakt Berechtigungen */
    public const CUSTOMER_CONTACT_PERMISSIONS = "customer-contact-permissions";
    /** Kundenkontakt  Förderungen */
    public const CUSTOMER_CONTACT_SUBSIDYS = "customer-contact-subsidys";
    /** Kundenkontakt */
    public const CUSTOMER_CONTACTS = "customer-contacts";
    /** Kunde Lieferadresse */
    public const CUSTOMER_DELIVERY_ADDRESS = "customer-delivery-address";
    /** Kundenabteilung */
    public const CUSTOMER_DEPARTMENTS = "customer-departments";
    /** Kunde Rabatt */
    public const CUSTOMER_DISCOUNTS = "customer-discounts";
    /** Kunde Dokumente */
    public const CUSTOMER_DOCUMENTS = "customer-documents";
    /** Kunde Schnittstelle */
    public const CUSTOMER_INTERFACES = "customer-interfaces";
    /** Kunde */
    public const CUSTOMERS = "customers";
    /** Zerlegung Struktur */
    public const CUTTING_STRUCTURES = "cutting-structures";
    /** Zerlegung */
    public const CUTTINGS = "cuttings";
    /** Dashboard */
    public const DASHBOARD_TEXTS = "dashboard-texts";
    /** Dashboard */
    public const DASHBOARD_USER_GROUPS = "dashboard-user-groups";
    /** Dashboard */
    public const DASHBOARD_WIDGET_TEXTS = "dashboard-widget-texts";
    /** Dashboard */
    public const DASHBOARD_WIDGETS = "dashboard-widgets";
    /** Dashboard */
    public const DASHBOARDS = "dashboards";
    /** Lieferprodukt Verpackungsreleation */
    public const DELIVERY_PRODUCTS_PACKAGING_RELATIONS = "delivery-products-packaging-relations";
    /** Lieferprodukt Verpackungsreleation Texte */
    public const DELIVERY_PRODUCTS_PACKAGING_RELATIONS_TEXTS = "delivery-products-packaging-relations-texts";
    /** Lieferprodukt Verpackungsstruktur */
    public const DELIVERY_PRODUCTS_PACKAGING_STRUCTURES = "delivery-products-packaging-structures";
    /** Pfandkonto */
    public const DEPOSIT_ACCOUNTS = "deposit-accounts";
    /** Pfandverknüpfung */
    public const DEPOSIT_LINKS = "deposit-links";
    /** Kostgruppe Texte */
    public const DIET_GROUP_TEXTS = "diet-group-texts";
    /** Kostgruppe */
    public const DIET_GROUPS = "diet-groups";
    /** Diätologieprofil Texte */
    public const DIET_PROFILE_TEXTS = "diet-profile-texts";
    /** Diätologieprofil */
    public const DIET_PROFILES = "diet-profiles";
    /** Kostform Texte */
    public const DIET_TYPE_TEXTS = "diet-type-texts";
    /** Kostform */
    public const DIET_TYPES = "diet-types";
    /** Eeaternity Prognose Details */
    public const EEATERNITY_FORECAST_DETAILS = "eeaternity-forecast-details";
    /** Eeaternity Prognose Infomeldungen */
    public const EEATERNITY_FORECAST_INFO_MESSAGES = "eeaternity-forecast-info-messages";
    /** Eeaternity Prognose */
    public const EEATERNITY_FORECASTS = "eeaternity-forecasts";
    /** Externe Bestellung Struktur */
    public const EXTERNAL_PURCHASE_ORDER_STRUCTURES = "external-purchase-order-structures";
    /** Externe Bestellung */
    public const EXTERNAL_PURCHASE_ORDERS = "external-purchase-orders";
    /** Funktionsnutzung */
    public const FEATURE_USAGES = "feature-usages";
    /** Fixkostenperiode Unterbrechungen */
    public const FIXED_COST_PERIOD_INTERRUPTIONS = "fixed-cost-period-interruptions";
    /** Fixkostenperiode Struktur */
    public const FIXED_COST_PERIOD_STRUCTURES = "fixed-cost-period-structures";
    /** Fixkostenperiode */
    public const FIXED_COST_PERIODS = "fixed-cost-periods";
    /** Durchflussmenge */
    public const FLOW_RATES = "flow-rates";
    /** Allgemeine Standardgarprogramme */
    public const GENERAL_STANDARD_COOKING_PROGRAMS = "general-standard-cooking-programs";
    /** Warenausgabe Struktur artikelbezogen */
    public const GOODS_ISSUE_STRUCTURE_BY_ITEMS = "goods-issue-structure-by-items";
    /** Warenausgabe Struktur */
    public const GOODS_ISSUE_STRUCTURES = "goods-issue-structures";
    /** Warenausgabe */
    public const GOODS_ISSUES = "goods-issues";
    /** Haccp-Anweisungen Texte */
    public const HACCP_INSTRUCTION_TEXTS = "haccp-instruction-texts";
    /** Haccp-Anweisungen */
    public const HACCP_INSTRUCTIONS = "haccp-instructions";
    /** Eingangslieferschein Dokumente */
    public const IN_DELIVERY_NOTE_DOCUMENTS = "in-delivery-note-documents";
    /** Lieferscheinbewertungskriterien */
    public const IN_DELIVERY_NOTE_EVALUATION_CRITERIAS = "in-delivery-note-evaluation-criterias";
    /** Lieferscheinbewertung Struktur */
    public const IN_DELIVERY_NOTE_EVALUATION_STRUCTURES = "in-delivery-note-evaluation-structures";
    /** Lieferscheinbewertung Texte */
    public const IN_DELIVERY_NOTE_EVALUATION_TEXTS = "in-delivery-note-evaluation-texts";
    /** Eingangslieferschein Wareneingangsprotokoll Definition Texte */
    public const IN_DELIVERY_NOTE_GOODS_RECEIPT_REPORT_DEFINITION_TEXTS = "in-delivery-note-goods-receipt-report-definition-texts";
    /** Eingangslieferschein Wareneingangsprotokoll Definition */
    public const IN_DELIVERY_NOTE_GOODS_RECEIPT_REPORT_DEFINITIONS = "in-delivery-note-goods-receipt-report-definitions";
    /** Eingangslieferschein Wareneingangsprotokoll Texte */
    public const IN_DELIVERY_NOTE_GOODS_RECEIPT_REPORT_TEXTS = "in-delivery-note-goods-receipt-report-texts";
    /** Eingangslieferschein Wareneingangsprotokoll Trigger */
    public const IN_DELIVERY_NOTE_GOODS_RECEIPT_REPORT_TRIGGERS = "in-delivery-note-goods-receipt-report-triggers";
    /** Eingangslieferschein Wareneingangsprotokoll */
    public const IN_DELIVERY_NOTE_GOODS_RECEIPT_REPORTS = "in-delivery-note-goods-receipt-reports";
    /** Eingangslieferschein Ersatzlieferungen */
    public const IN_DELIVERY_NOTE_REPLACEMENT_DELIVERYS = "in-delivery-note-replacement-deliverys";
    /** Eingangslieferschein Struktur Splitbuchung */
    public const IN_DELIVERY_NOTE_STRUCTURE_SPLIT_BOOKINGS = "in-delivery-note-structure-split-bookings";
    /** Eingangslieferschein Struktur */
    public const IN_DELIVERY_NOTE_STRUCTURES = "in-delivery-note-structures";
    /** Eingangslieferschein */
    public const IN_DELIVERY_NOTES = "in-delivery-notes";
    /** Eingangsrechnung Kontierung */
    public const IN_INVOICE_ACCOUNT_ASSIGNMENTS = "in-invoice-account-assignments";
    /** Eingangsrechnung Dokumente */
    public const IN_INVOICE_DOCUMENTS = "in-invoice-documents";
    /** Eingangsrechnung Struktur Kostenstellensplittung */
    public const IN_INVOICE_STRUCTURE_COST_CENTER_SPLITS = "in-invoice-structure-cost-center-splits";
    /** Eingangsrechnung Struktur */
    public const IN_INVOICE_STRUCTURES = "in-invoice-structures";
    /** Eingangsrechnung */
    public const IN_INVOICES = "in-invoices";
    /** Inventurstand artikelbezogen */
    public const INVENTORY_BALANCE_BY_ITEMS = "inventory-balance-by-items";
    /** Inventurstand */
    public const INVENTORY_BALANCES = "inventory-balances";
    /** Inventurabschluss Lagerbewertung */
    public const INVENTORY_CLOSING_STOCK_VALUATIONS = "inventory-closing-stock-valuations";
    /** Lagerbewegungsnotiz */
    public const INVENTORY_MOVEMENT_NOTES = "inventory-movement-notes";
    /** Lagerbewegungen */
    public const INVENTORY_MOVEMENTS = "inventory-movements";
    /** Inventurabschluss/Periodenabschluss */
    public const INVENTORY_PERIOD_CLOSINGS = "inventory-period-closings";
    /** Sprache */
    public const LANGUAGES = "languages";
    /** List&Label Layout */
    public const LIST_LABEL_LAYOUTS = "list-label-layouts";
    /** LMIV Key Test */
    public const LMIV_KEY_TESTS = "lmiv-key-tests";
    /** LMIV Key */
    public const LMIV_KEYS = "lmiv-keys";
    /** LMIV Keyword */
    public const LMIV_KEYWORDS = "lmiv-keywords";
    /** Maschinencode Texte */
    public const MACHINE_CODE_TEXTS = "machine-code-texts";
    /** Maschinencode */
    public const MACHINE_CODES = "machine-codes";
    /** MBS Menübestellprofil Struktur */
    public const MBS_MENU_ORDER_PROFILE_STRUCTURES = "mbs-menu-order-profile-structures";
    /** MBS Menübestellprofil */
    public const MBS_MENU_ORDER_PROFILES = "mbs-menu-order-profiles";
    /** Mahlzeit Texte */
    public const MEAL_TEXTS = "meal-texts";
    /** Mahlzeit */
    public const MEALS = "meals";
    /** Menüplanklasse */
    public const MENU_CLASS = "menu-class";
    /** Menüplanklassenbudget Details */
    public const MENU_CLASS_BUDGET_DETAILS = "menu-class-budget-details";
    /** Menüplanklassenbudget */
    public const MENU_CLASS_BUDGETS = "menu-class-budgets";
    /** Menüplanpreise (obsolet) */
    public const MENU_CLASS_PRICES = "menu-class-prices";
    /** Menüplanklassen Texte */
    public const MENU_CLASS_TEXTS = "menu-class-texts";
    /** Menüplanpreise */
    public const MENU_PLAN_PRICES = "menu-plan-prices";
    /** Menüplanpreise Stufenfunktion */
    public const MENU_PLAN_PRICES_STEP_FUNCTIONS = "menu-plan-prices-step-functions";
    /** Menüplanprofilbudget */
    public const MENU_PLAN_PROFILE_BUDGETS = "menu-plan-profile-budgets";
    /** Menüplanprofil Verknüfpungen */
    public const MENU_PLAN_PROFILE_CONNECTIONS = "menu-plan-profile-connections";
    /** Menüplanprofil Deadline Mahlzeit */
    public const MENU_PLAN_PROFILE_DEADLINE_MEALS = "menu-plan-profile-deadline-meals";
    /** Menüplanprofil Lieferzeiten */
    public const MENU_PLAN_PROFILE_DELIVERY_TIME_TEXTS = "menu-plan-profile-delivery-time-texts";
    /** Menüplanprofil Lieferzeiten */
    public const MENU_PLAN_PROFILE_DELIVERY_TIMES = "menu-plan-profile-delivery-times";
    /** Menüplanprofil Texte */
    public const MENU_PLAN_PROFILE_TEXTS = "menu-plan-profile-texts";
    /** Menüplanprofil */
    public const MENU_PLAN_PROFILES = "menu-plan-profiles";
    /** Menüvorbestellung Zusatzbestellung */
    public const MENU_PRE_ORDER_ADDITIONAL_ORDERS = "menu-pre-order-additional-orders";
    /** Menüvorbestellung Notiz */
    public const MENU_PRE_ORDER_NOTES = "menu-pre-order-notes";
    /** Menüvorbestellung Bestellanforderung */
    public const MENU_PRE_ORDER_ORDER_REQUISITIONS = "menu-pre-order-order-requisitions";
    /** Menüvorbestellung Struktur */
    public const MENU_PRE_ORDER_STRUCTURES = "menu-pre-order-structures";
    /** Menüvorbestellung */
    public const MENU_PRE_ORDERS = "menu-pre-orders";
    /** Menüvorbestllung Verteilungsschlüssel */
    public const MENU_PREORDERING_DISTRIBUTION_KEYS = "menu-preordering-distribution-keys";
    /** Menüplanmengen (Obsolet) */
    public const MENU_QUANTITIES = "menu-quantities";
    /** Menüplanprofilbudget Struktur */
    public const MENUE_PLAN_PROFIL_BUDGET_STRUCTURES = "menue-plan-profil-budget-structures";
    /** Nachrichtenempfänger */
    public const MESSAGE_RECIPIENTS = "message-recipients";
    /** Nachricht */
    public const MESSAGES = "messages";
    /** Microbakteorologische Bewertung Struktur Texte */
    public const MICROBACTERIOLOGICAL_EVALUATION_STRUCTURE_TEXTS = "microbacteriological-evaluation-structure-texts";
    /** Microbakteorologische Bewertung Struktur */
    public const MICROBACTERIOLOGICAL_EVALUATION_STRUCTURES = "microbacteriological-evaluation-structures";
    /** Microbakteorologische Bewertung Texte */
    public const MICROBACTERIOLOGICAL_EVALUATION_TEXTS = "microbacteriological-evaluation-texts";
    /** Microbakteorologische Bewertung */
    public const MICROBACTERIOLOGICAL_EVALUATIONS = "microbacteriological-evaluations";
    /** Attribut Texte */
    public const NECTA_ATTRIBUTE_TEXTS = "necta-attribute-texts";
    /** Attribut */
    public const NECTA_ATTRIBUTES = "necta-attributes";
    /** Nährwertklasse */
    public const NUTRIENT_CLASS = "nutrient-class";
    /** Nährwertklasse Texte */
    public const NUTRIENT_CLASS_TEXTS = "nutrient-class-texts";
    /** Nährwertgruppe Struktur */
    public const NUTRIENT_GROUP_STRUCTURES = "nutrient-group-structures";
    /** Nährwertgruppe Texte */
    public const NUTRIENT_GROUP_TEXTS = "nutrient-group-texts";
    /** Nährwertgruppe */
    public const NUTRIENT_GROUPS = "nutrient-groups";
    /** Nährwertempfehlungen */
    public const NUTRIENT_RECOMMENDATIONS = "nutrient-recommendations";
    /** Nährwert Texte */
    public const NUTRIENT_TEXTS = "nutrient-texts";
    /** Nährwert */
    public const NUTRIENTS = "nutrients";
    /** NUTS Region Texte */
    public const NUTS_REGION_TEXTS = "nuts-region-texts";
    /** NUTS Region */
    public const NUTS_REGIONS = "nuts-regions";
    /** Objektklassenzuordnungen */
    public const OBJECT_CLASS_ASSIGNMENTS = "object-class-assignments";
    /** Objektklassenzuordnungen Kostenstellenspezifisch */
    public const OBJECT_CLASS_ASSIGNMENTS_COST_CENTER_SPECIFICS = "object-class-assignments-cost-center-specifics";
    /** Optimix Bereich */
    public const OPTIMIX_AREAS = "optimix-areas";
    /** Optimixdefinitonen */
    public const OPTIMIX_DEFINITIONS = "optimix-definitions";
    /** Optimix Typ */
    public const OPTIMIX_TYPES = "optimix-types";
    /** Bestellanforderung Texte */
    public const ORDER_REQUISITION_TEXTS = "order-requisition-texts";
    /** Bestellanforderung */
    public const ORDER_REQUISITIONS = "order-requisitions";
    /** Auftrag Struktur Bestellanforderungen */
    public const ORDER_STRUCTURE_ORDER_REQUISITIONS = "order-structure-order-requisitions";
    /** Auftrag Struktur */
    public const ORDER_STRUCTURES = "order-structures";
    /** Auftrag */
    public const ORDERS = "orders";
    /** Ausgangslieferschein Dokumente */
    public const OUT_DELIVERY_NOTE_DOCUMENTS = "out-delivery-note-documents";
    /** Ausgangslieferschein Struktur */
    public const OUT_DELIVERY_NOTE_STRUCTURES = "out-delivery-note-structures";
    /** Ausgangslieferschein */
    public const OUT_DELIVERY_NOTES = "out-delivery-notes";
    /** Ausgangsrechnung Struktur */
    public const OUT_INVOICE_STRUCTURES = "out-invoice-structures";
    /** Ausgangsrechnung */
    public const OUT_INVOICES = "out-invoices";
    /** Ausgangsrechnung Kontierung */
    public const OUT_INVOICES_ACCOUNT_ASSIGNMENTS = "out-invoices-account-assignments";
    /** Verpackung Vebrauch */
    public const PACKAGING_CONSUMPTIONS = "packaging-consumptions";
    /** Verpackung Produkte */
    public const PACKAGING_PRODUCTS = "packaging-products";
    /** Verpackung */
    public const PACKAGINGS = "packagings";
    /** Verpackstation Texte */
    public const PACKING_STATION_TEXTS = "packing-station-texts";
    /** Verpackstation */
    public const PACKING_STATIONS = "packing-stations";
    /** Teilproduktion Verbrauch */
    public const PARTIAL_PRODUCTION_CONSUMPTIONS = "partial-production-consumptions";
    /** Teilproduktion */
    public const PARTIAL_PRODUCTIONS = "partial-productions";
    /** Zahlung */
    public const PAYMENTS = "payments";
    /** Kommissionierliste Struktur */
    public const PICKING_LIST_STRUCTURES = "picking-list-structures";
    /** Kommissionierliste */
    public const PICKING_LISTS = "picking-lists";
    /** Vorbestellmengen */
    public const PRE_ORDER_QUANTITYS = "pre-order-quantitys";
    /** Preisquarantäne Struktur */
    public const PRICE_QUARANTINE_STRUCTURES = "price-quarantine-structures";
    /** Preisquarantäne */
    public const PRICE_QUARANTINES = "price-quarantines";
    /** Produkt Allergene */
    public const PRODUCT_ALLERGENS = "product-allergens";
    /** Freigabe Produktbereich für anderen Mandanten */
    public const PRODUCT_AREA_RELEASED_FOR_OTHER_TENANTS = "product-area-released-for-other-tenants";
    /** Produktbereich Texte */
    public const PRODUCT_AREA_TEXTS = "product-area-texts";
    /** Produktbereich */
    public const PRODUCT_AREAS = "product-areas";
    /** Produkt Kalkulation */
    public const PRODUCT_CALCULATIONS = "product-calculations";
    /** Änderungshitorie Produkte */
    public const PRODUCT_CHANGE_HISTORYS = "product-change-historys";
    /** Produktklasse */
    public const PRODUCT_CLASS = "product-class";
    /** Produktklassensteuerarchiv Details */
    public const PRODUCT_CLASS_TAX_ARCHIVE_DETAILS = "product-class-tax-archive-details";
    /** Produktklassensteuerarchiv */
    public const PRODUCT_CLASS_TAX_ARCHIVS = "product-class-tax-archivs";
    /** Produktklasse Texte */
    public const PRODUCT_CLASS_TEXTS = "product-class-texts";
    /** Produktklassifizierung */
    public const PRODUCT_CLASSIFICATIONS = "product-classifications";
    /** Produktklausel Texte */
    public const PRODUCT_CLAUSE_TEXTS = "product-clause-texts";
    /** Produktklausel */
    public const PRODUCT_CLAUSES = "product-clauses";
    /** Produkt kostenstellespezifische Werte */
    public const PRODUCT_COST_CENTER_SPECIFIC_VALUES = "product-cost-center-specific-values";
    /** Produkt Deklarationspflichtige Stoffe */
    public const PRODUCT_DECLARATION_SUBSTANCES = "product-declaration-substances";
    /** Produkt Dokumente */
    public const PRODUCT_DOCUMENTS = "product-documents";
    /** Produkt EAN */
    public const PRODUCT_EANS = "product-eans";
    /** Produktbilder */
    public const PRODUCT_IMAGES = "product-images";
    /** Produkt Zutatenliste Texte */
    public const PRODUCT_INGREDIENT_LIST_TEXTS = "product-ingredient-list-texts";
    /** Produkt Zutatenliste */
    public const PRODUCT_INGREDIENT_LISTS = "product-ingredient-lists";
    /** Produktlabels */
    public const PRODUCT_LABELS = "product-labels";
    /** Produkt Nährwerte */
    public const PRODUCT_NUTRITIONAL_VALUES = "product-nutritional-values";
    /** Produkt Optimixkriterien */
    public const PRODUCT_OPTIMIX_CRITERIAS = "product-optimix-criterias";
    /** Produkt Herkunft kostenstellenspezfisich */
    public const PRODUCT_ORIGIN_COST_CENTER_SPECIFICS = "product-origin-cost-center-specifics";
    /** Produktpreishistorie */
    public const PRODUCT_PRICE_HISTORYS = "product-price-historys";
    /** Produkt Spezifikation Texte */
    public const PRODUCT_SPECIFICATION_TEXTS = "product-specification-texts";
    /** Produkt Spezifikation */
    public const PRODUCT_SPECIFICATIONS = "product-specifications";
    /** Produkt SSCC */
    public const PRODUCT_SSCCS = "product-ssccs";
    /** Produkt Struktur Texte */
    public const PRODUCT_STRUCTURE_TEXTS = "product-structure-texts";
    /** Produkt Struktur */
    public const PRODUCT_STRUCTURES = "product-structures";
    /** Produkt Texte */
    public const PRODUCT_TEXTS = "product-texts";
    /** Produkt Relationen */
    public const PRODUCT_UNIT_RELATIONS = "product-unit-relations";
    /** Produktvideos */
    public const PRODUCT_VIDEOS = "product-videos";
    /** Produktion Verbrauch Gruppe */
    public const PRODUCTION_CONSUMPTION_GROUPS = "production-consumption-groups";
    /** Produktion Verbrauch */
    public const PRODUCTION_CONSUMPTIONS = "production-consumptions";
    /** Produktion Transportboxenbedarf */
    public const PRODUCTION_CONTAINER_BOX_DEMANDS = "production-container-box-demands";
    /** Produktion Kochprogramme */
    public const PRODUCTION_COOKING_PROGRAMS = "production-cooking-programs";
    /** Produktion - Verteilung */
    public const PRODUCTION_DISTRIBUTIONS = "production-distributions";
    /** Produktionsgruppe Texte */
    public const PRODUCTION_GROUP_TEXTS = "production-group-texts";
    /** Produktionsgruppe */
    public const PRODUCTION_GROUPS = "production-groups";
    /** Produktionslinie Texte */
    public const PRODUCTION_LINE_TEXTS = "production-line-texts";
    /** Produktionslinie */
    public const PRODUCTION_LINES = "production-lines";
    /** Produktion Produkte */
    public const PRODUCTION_PRODUCTS = "production-products";
    /** Produtkionsabgleich Struktur */
    public const PRODUCTION_RECONCILIATION_STRUCTURES = "production-reconciliation-structures";
    /** Produtkionsabgleich */
    public const PRODUCTION_RECONCILIATIONS = "production-reconciliations";
    /** Produktionsort Texte */
    public const PRODUCTION_SITE_TEXTS = "production-site-texts";
    /** Produktionsort */
    public const PRODUCTION_SITES = "production-sites";
    /** Produktion Struktur */
    public const PRODUCTION_STRUCTURES = "production-structures";
    /** Produktion */
    public const PRODUCTIONS = "productions";
    /** Produkt */
    public const PRODUCTS = "products";
    /** Bestellung produktbezogener Bedarf */
    public const PURCHASE_ORDER_PRODUCT_RELATED_DEMANDS = "purchase-order-product-related-demands";
    /** Bestellung Bestellreferenz (Summen/Status) Statushistorie */
    public const PURCHASE_ORDER_REFERENCE_STATUS_HISTORYS = "purchase-order-reference-status-historys";
    /** Bestellung Bestellreferenz (Summen/Status) */
    public const PURCHASE_ORDER_REFERENCES = "purchase-order-references";
    /** Bestellung Struktur */
    public const PURCHASE_ORDER_STRUCTURES = "purchase-order-structures";
    /** Bestellung */
    public const PURCHASE_ORDERS = "purchase-orders";
    /** Reduziertes Menüplanprofil Texte */
    public const REDUCED_MENU_PLAN_PROFILE_TEXTS = "reduced-menu-plan-profile-texts";
    /** Reduziertes Menüplanprofil */
    public const REDUCED_MENU_PLAN_PROFILES = "reduced-menu-plan-profiles";
    /** Relation Rückstellung - Eingangslieferschein */
    public const REL_ACCRUAL2_IN_DELIVERY_NOTES = "rel-accrual2-in-delivery-notes";
    /** Relation Bandpostengruppe -  Kostenstelle */
    public const REL_BELT_POST_GROUP2_COST_CENTERS = "rel-belt-post-group2-cost-centers";
    /** Relation Bandposten - Externe Produktklasse */
    public const REL_BELT_POST_GROUP2_PRODUCT_CLASS = "rel-belt-post-group2-product-class";
    /** Relation Kalender -  Kostenstelle */
    public const REL_CALENDAR2_COST_CENTERS = "rel-calendar2-cost-centers";
    /** Relation Verpflegsart -  Kostenstelle */
    public const REL_CATERING_TYPE2_COST_CENTERS = "rel-catering-type2-cost-centers";
    /** Relation Klassifizierung -  Kunde */
    public const REL_CLASSIFICATION2_CUSTOMERS = "rel-classification2-customers";
    /** Relation Klassifizierung -  Lieferant */
    public const REL_CLASSIFICATION2_SUPPLIERS = "rel-classification2-suppliers";
    /** Relation Transportbox - Behälter */
    public const REL_CONTAINER_BOX2_CONTAINERS = "rel-container-box2-containers";
    /** Relation Objektklasse -  Kostenstelle */
    public const REL_COST_CENTER_CLASS2_COST_CENTERS = "rel-cost-center-class2-cost-centers";
    /** Relation Kundenkontakt - Attribute */
    public const REL_CUSTOMER_CONTACT2_NECTA_ATTRIBUTES = "rel-customer-contact2-necta-attributes";
    /** Relation Kundenlieferadresse - Produktionslinie */
    public const REL_CUSTOMER_DELIVERY_ADDRESS2_PRODUCTION_LINES = "rel-customer-delivery-address2-production-lines";
    /** Relation Kunde - Kostenstelle */
    public const REL_CUSTOMER2_COST_CENTERS = "rel-customer2-cost-centers";
    /** Relation Diätologieprofil - Kostformen */
    public const REL_DIET_PROFILE2_DIET_TYPES = "rel-diet-profile2-diet-types";
    /** Relation Eingangslieferschein - Bestellung */
    public const REL_IN_DELIVERY_NOTE2_PURCHASE_ORDERS = "rel-in-delivery-note2-purchase-orders";
    /** Relation Eingangsrechnung - Eingangslieferschein */
    public const REL_IN_INVOICE2_IN_DELIVERY_NOTES = "rel-in-invoice2-in-delivery-notes";
    /** Relation List&Label Layout - Kostenstelle */
    public const REL_LIST_LABEL_LAYOUT2_COST_CENTERS = "rel-list-label-layout2-cost-centers";
    /** Relation Menüplanklasse -  Kostenstellen */
    public const REL_MENU_CLASS2_COST_CENTERS = "rel-menu-class2-cost-centers";
    /** Relationen Menüplanklassen - Kunden */
    public const REL_MENU_CLASS2_CUSTOMERS = "rel-menu-class2-customers";
    /** Relation Menüplanprofil - Verpflegsart */
    public const REL_MENU_PLAN_PROFILE2_CATERING_TYPES = "rel-menu-plan-profile2-catering-types";
    /** Relation Menüplanprofil - Bestellanforderung */
    public const REL_MENU_PLAN_PROFILE2_ORDER_REQUISITIONS = "rel-menu-plan-profile2-order-requisitions";
    /** Relation Menüplanprofil - Produktklasse */
    public const REL_MENU_PLAN_PROFILE2_PRODUCT_CLASS = "rel-menu-plan-profile2-product-class";
    /** Relation Wochenmenüvorbestellung -  Auftrag */
    public const REL_MENU_PRE_ORDER2_ORDERS = "rel-menu-pre-order2-orders";
    /** Relation Attribut -  Kostenstelle */
    public const REL_NECTA_ATTRIBUTE2_COST_CENTERS = "rel-necta-attribute2-cost-centers";
    /** Relation Ausgangslieferschen - Auftragszeile */
    public const REL_OUT_DELIVERY_NOTE2_ORDER_STRUCTURES = "rel-out-delivery-note2-order-structures";
    /** Relation Ausgangslieferschen - Auftrag */
    public const REL_OUT_DELIVERY_NOTE2_ORDERS = "rel-out-delivery-note2-orders";
    /** Relation Ausgangsrechnung - Eingangslieferschein */
    public const REL_OUT_INVOICE2_IN_DELIVERY_NOTES = "rel-out-invoice2-in-delivery-notes";
    /** Relation Ausgangsrechnung - Auftrag/Ausgangslieferschein */
    public const REL_OUT_INVOICE2_ORDER2_OUT_DELIVERY_NOTES = "rel-out-invoice2-order2-out-delivery-notes";
    /** Relation Teilproduktion - Produktion */
    public const REL_PARTIAL_PRODUCTION2_PRODUCTIONS = "rel-partial-production2-productions";
    /** Relation Kommissionierliste - Ausgangslieferschein */
    public const REL_PICKING_LIST2_OUT_DELIVERY_NOTES = "rel-picking-list2-out-delivery-notes";
    /** Relation Produktbereich -  Kostenstelle */
    public const REL_PRODUCT_AREA_COST_CENTERS = "rel-product-area-cost-centers";
    /** Relation Produktklasse -  Kostenstelle  */
    public const REL_PRODUCT_CLASS2_COST_CENTERS = "rel-product-class2-cost-centers";
    /** Relation Produktklasse -  Kostenstelle  */
    public const REL_PRODUCT_CLASS2_TENANTS = "rel-product-class2-tenants";
    /** Reation Produkt - Container */
    public const REL_PRODUCT2_CONTAINERS = "rel-product2-containers";
    /** Relation Produkt - Kostenstelle - Lager */
    public const REL_PRODUCT2_COST_CENTER2_STOCKS = "rel-product2-cost-center2-stocks";
    /** Relation Kostform - Produkt */
    public const REL_PRODUCT2_DIET_TYPES = "rel-product2-diet-types";
    /** Relation Produkt - HACCP Anweisung */
    public const REL_PRODUCT2_HACCP_INSTRUCTIONS = "rel-product2-haccp-instructions";
    /** Relation Produkt - Attribut */
    public const REL_PRODUCT2_NECTA_ATTRIBUTES = "rel-product2-necta-attributes";
    /** Relation Produkt - Produktklausel */
    public const REL_PRODUCT2_PRODUCT_CLAUSES = "rel-product2-product-clauses";
    /** Relation Produktionsort - Kostenstelle */
    public const REL_PRODUCTION_SITE2_COST_CENTERS = "rel-production-site2-cost-centers";
    /** Relation Produktion Struktur Personalie */
    public const REL_PRODUCTION_STRUCTURE2_STAFF_MEMBERS = "rel-production-structure2-staff-members";
    /** Releation Produktion - Auftrag */
    public const REL_PRODUCTION2_ORDERS = "rel-production2-orders";
    /** Relation Musterbestellungsposition - Kostenstelle */
    public const REL_PURCHASE_ORDER_STRUCTURE2_COST_CENTERS = "rel-purchase-order-structure2-cost-centers";
    /** Gültigkeit Bestellung - Kostenstelle */
    public const REL_PURCHASE_ORDER2_COST_CENTERS = "rel-purchase-order2-cost-centers";
    /** Relation Reduziertes Menüplanprofil - Verpflegsarten */
    public const REL_REDUCED_MENU_PLAN_PROFILE2_CATERING_TYPES = "rel-reduced-menu-plan-profile2-catering-types";
    /** Reduziertes Menüplanprofil Kundenkontakt */
    public const REL_REDUCED_MENU_PLAN_PROFILE2_CUSTOMER_CONTACTS = "rel-reduced-menu-plan-profile2-customer-contacts";
    /** Relation Verkaufsliste - Kostenstelle */
    public const REL_SALES_LIST2_COST_CENTERS = "rel-sales-list2-cost-centers";
    /** Relation Verkaufsliste - Kunde */
    public const REL_SALES_LIST2_CUSTOMERS = "rel-sales-list2-customers";
    /** Relation Verkaufsliste - Produktionslinie */
    public const REL_SALES_LIST2_PRODUCTION_LINES = "rel-sales-list2-production-lines";
    /** Relation Lager -  Produkt */
    public const REL_STOCK2_PRODUCTS = "rel-stock2-products";
    /** Relation Artikel - Kostenstelle */
    public const REL_SUPPLIER_ITEM2_COST_CENTERS = "rel-supplier-item2-cost-centers";
    /** Mandatenspezifische Relation Lieferantenartikel - Attribut */
    public const REL_SUPPLIER_ITEM2_NECTA_ATTRIBUTES = "rel-supplier-item2-necta-attributes";
    /** Relation Lieferantenartikel - necta Produktklassenbaum */
    public const REL_SUPPLIER_ITEM2_PRODUCT_CLASS = "rel-supplier-item2-product-class";
    /** Relation Lieferant - Kostenstelle */
    public const REL_SUPPLIER2_COST_CENTERS = "rel-supplier2-cost-centers";
    /** Relation Lieferant - Mandant */
    public const REL_SUPPLIER2_TENANTS = "rel-supplier2-tenants";
    /** Relation Aufschlagsklasse -  Kostenstelle */
    public const REL_SURCHARGE_CLASS2_COST_CENTERS = "rel-surcharge-class2-cost-centers";
    /** Relation Auschreibung Lieferanten - Produktklasse */
    public const REL_TENDERING_SUPPLIER2_PRODUCT_CLASS = "rel-tendering-supplier2-product-class";
    /** Relation Ausschreibung - Objektklasse */
    public const REL_TENDERING2_COST_CENTER_CLASS = "rel-tendering2-cost-center-class";
    /** Relation Tour - Kunde */
    public const REL_TOUR2_CUSTOMERS = "rel-tour2-customers";
    /** Relation User - Kostenstelle */
    public const REL_USER_DEF2_COST_CENTERS = "rel-user-def2-cost-centers";
    /** Relation User - Kunde */
    public const REL_USER_DEF2_CUSTOMERS = "rel-user-def2-customers";
    /** Relation User -Report - Kostenstelle */
    public const REL_USER_DEF2_REPORT2_COST_CENTERS = "rel-user-def2-report2-cost-centers";
    /** Relation Wochenproduktion - Produktion */
    public const REL_WEEK_PRODUCTION2_PRODUCTIONS = "rel-week-production2-productions";
    /** Relation Wochenmenüplan -  Kostenstelle */
    public const REL_WEEKLY_MENU_PLAN2_COST_CENTERS = "rel-weekly-menu-plan2-cost-centers";
    /** Berichtsgruppe */
    public const REPORT_GROUPS = "report-groups";
    /** Reportemplate Texte */
    public const REPORT_TEMPLATE_TEXTS = "report-template-texts";
    /** Reportemplate */
    public const REPORT_TEMPLATES = "report-templates";
    /** Report */
    public const REPORTS = "reports";
    /** Verkaufsliste Aktionszeitraum */
    public const SALES_LIST_PROMOTION_PERIODS = "sales-list-promotion-periods";
    /** Verkaufsliste Struktur Minimum/Maximumbestellmengen */
    public const SALES_LIST_STRUCTURE_MIN_MAX_ORDER_QUANTITYS = "sales-list-structure-min-max-order-quantitys";
    /** Verkaufsliste Stuktur Aktionspreise */
    public const SALES_LIST_STRUCTURE_PROMOTION_PRICES = "sales-list-structure-promotion-prices";
    /** Verkaufsliste Struktur Texte */
    public const SALES_LIST_STRUCTURE_TEXTS = "sales-list-structure-texts";
    /** Verkaufsliste Struktur */
    public const SALES_LIST_STRUCTURES = "sales-list-structures";
    /** Verkaufsliste Texte */
    public const SALES_LIST_TEXTS = "sales-list-texts";
    /** Verkaufsliste Staffelrpeise (obsolet) */
    public const SALES_LIST_TIER_PRICES_OBSOLETES = "sales-list-tier-prices-obsoletes";
    /** Verkaufsliste */
    public const SALES_LISTS = "sales-lists";
    /** Verkaufsstatistik Struktur */
    public const SALES_STATISTIC_STRUCTURES = "sales-statistic-structures";
    /** Verkaufsstatistik */
    public const SALES_STATISTICS = "sales-statistics";
    /** Sonderausgaben/-einnahmen */
    public const SPECIAL_EXPENSES_INCOME_ENTRYS = "special-expenses-income-entrys";
    /** Nachrichten Standardtext */
    public const STANDARD_MESSAGE_TEXTS = "standard-message-texts";
    /** Dauerauftrag Struktur Bestellanforderung */
    public const STANDING_ORDER_STRUCTURE_ORDER_REQUISITIONS = "standing-order-structure-order-requisitions";
    /** Dauerauftrag Struktur */
    public const STANDING_ORDER_STRUCTURES = "standing-order-structures";
    /** Dauerauftrag */
    public const STANDING_ORDERS = "standing-orders";
    /** Stockdefintion */
    public const STOCK_DEFINITIONS = "stock-definitions";
    /** Lagerort */
    public const STOCK_LOCATIONS = "stock-locations";
    /** Lager Texte */
    public const STOCK_TEXTS = "stock-texts";
    /** Lager */
    public const STOCKS = "stocks";
    /** Statistik versandte Bestellungen */
    public const SUM_SENT_PURCHASE_ORDERS = "sum-sent-purchase-orders";
    /** Lieferanten Kontaktdaten */
    public const SUPPLIER_CONTACT_DATAS = "supplier-contact-datas";
    /** Lieferantenliefergebiet */
    public const SUPPLIER_DELIVERY_AREAS = "supplier-delivery-areas";
    /** Lieferant Dokumente */
    public const SUPPLIER_DOCUMENTS = "supplier-documents";
    /** Lieferantenaritkel Zusatzinformationen (Pistor) */
    public const SUPPLIER_ITEM_ADDITIONAL_INFORMATIONS = "supplier-item-additional-informations";
    /** Lieferantenartikel Allergene */
    public const SUPPLIER_ITEM_ALLERGENS = "supplier-item-allergens";
    /** Lieferantenartikel  Allergene Historie */
    public const SUPPLIER_ITEM_ALLERGENS_HISTORYS = "supplier-item-allergens-historys";
    /** Artikelzuordnung in Rezept */
    public const SUPPLIER_ITEM_ASSIGNMENT_IN_RECIPES = "supplier-item-assignment-in-recipes";
    /** Artikelpuffer */
    public const SUPPLIER_ITEM_BUFFERS = "supplier-item-buffers";
    /** Artikelpuffer Status */
    public const SUPPLIER_ITEM_BUFFERS_STATUS = "supplier-item-buffers-status";
    /** Warengruppe Texte (für künftige Entwicklungen) */
    public const SUPPLIER_ITEM_COMMODITY_GROUP_TEXTS = "supplier-item-commodity-group-texts";
    /** Warengruppe (für künftige Entwicklungen) */
    public const SUPPLIER_ITEM_COMMODITY_GROUPS = "supplier-item-commodity-groups";
    /** Artikelkontrollpreis */
    public const SUPPLIER_ITEM_CONTROL_PRICES = "supplier-item-control-prices";
    /** Lieferantenartikel Deklarationspflichtige Stoffe */
    public const SUPPLIER_ITEM_DECLARATION_SUBSTANCES = "supplier-item-declaration-substances";
    /** Lieferantenartikel Deklarationspflichtige Stoffe Historie */
    public const SUPPLIER_ITEM_DECLARATION_SUBSTANCES_HISTORYS = "supplier-item-declaration-substances-historys";
    /** Lieferantenartikel Eingegebene LMIV-Werte */
    public const SUPPLIER_ITEM_ENTERED_LMIV_VALUES = "supplier-item-entered-lmiv-values";
    /** Lieferantenartikelhistorie */
    public const SUPPLIER_ITEM_HISTORYS = "supplier-item-historys";
    /** Lieferantenartikel Nährwerte */
    public const SUPPLIER_ITEM_NUTRITIONAL_VALUES = "supplier-item-nutritional-values";
    /** Lieferantenartikel Nährwerte Historie */
    public const SUPPLIER_ITEM_NUTRITIONAL_VALUES_HISTORYS = "supplier-item-nutritional-values-historys";
    /** Artikelpreis kostenstellenspezifisch */
    public const SUPPLIER_ITEM_PRICE_COST_CENTER_SPECIFICS = "supplier-item-price-cost-center-specifics";
    /** Artikelpreishistorie */
    public const SUPPLIER_ITEM_PRICE_HISTORYS = "supplier-item-price-historys";
    /** Artikelpreis */
    public const SUPPLIER_ITEM_PRICES = "supplier-item-prices";
    /** Lieferantenvereinbarung */
    public const SUPPLIER_ITEM_SUPPLIER_CONTRACTS = "supplier-item-supplier-contracts";
    /** Lieferantenartikel Texte */
    public const SUPPLIER_ITEM_TEXTS = "supplier-item-texts";
    /** Lieferantenartikel */
    public const SUPPLIER_ITEMS = "supplier-items";
    /** Lieferanten Produktklassenrabatte */
    public const SUPPLIER_PRODUCT_CLASS_DISCOUNTS = "supplier-product-class-discounts";
    /** Lieferant mandatenspezifische Einstellungen */
    public const SUPPLIER_TENANT_SPECIFIC_SETTINGS = "supplier-tenant-specific-settings";
    /** Lieferanteneinheiten mandatenspezifisch */
    public const SUPPLIER_UNITS_TENANT_SPECIFICS = "supplier-units-tenant-specifics";
    /** Lieferant */
    public const SUPPLIERS = "suppliers";
    /** Aufschlagsklassen */
    public const SURCHARGE_CLASS = "surcharge-class";
    /** Aufschlagsklasse Texte */
    public const SURCHARGE_CLASS_TEXTS = "surcharge-class-texts";
    /** Aufschläge hauptkostenstellenspezifisch */
    public const SURCHARGES_MAIN_COST_CENTER_SPECIFICS = "surcharges-main-cost-center-specifics";
    /** Mandantenspezifische Aufschlagsklasse */
    public const SURCHARGES_TENANT_SPECIFICS = "surcharges-tenant-specifics";
    /** Teammitglieder */
    public const TEAM_MEMBERS = "team-members";
    /** Team */
    public const TEAMS = "teams";
    /** Musterauftrag Struktur */
    public const TEMPLATE_ORDER_STRUCTURES = "template-order-structures";
    /** Musterauftrag Texte */
    public const TEMPLATE_ORDER_TEXTS = "template-order-texts";
    /** Musterauftrag */
    public const TEMPLATE_ORDERS = "template-orders";
    /** Ausschreibung Artikel */
    public const TENDERING_ITEMS = "tendering-items";
    /** Ausschreibung Artikel Archiv */
    public const TENDERING_ITEMS_ARCHIVS = "tendering-items-archivs";
    /** Ausschreibung Struktur */
    public const TENDERING_STRUCTURES = "tendering-structures";
    /** Ausschreibung Lieferanten */
    public const TENDERING_SUPPLIERS = "tendering-suppliers";
    /** Ausschreibung */
    public const TENDERINGS = "tenderings";
    /** Tour Texte */
    public const TOUR_TEXTS = "tour-texts";
    /** Tour */
    public const TOURS = "tours";
    /** Einheit Texte */
    public const UNIT_TEXTS = "unit-texts";
    /** Einheit */
    public const UNITS = "units";
    /** User */
    public const USER_DEFS = "user-defs";
    /** Usergruppe Berechtigung für Drucklayouts */
    public const USER_GROUP_PRINT_LAYOUT_PERMISSIONS = "user-group-print-layout-permissions";
    /** UsergruppeBerechtigung Reporttemplate */
    public const USER_GROUP_REPORT_TEMPLATE_PERMISSIONS = "user-group-report-template-permissions";
    /** User Reportkonfiguration */
    public const USER_REPORT_CONFIGURATIONS = "user-report-configurations";
    /** Wochenproduktion Struktur */
    public const WEEK_PRODUCTION_STRUCTURES = "week-production-structures";
    /** Wochenproduktion */
    public const WEEK_PRODUCTIONS = "week-productions";
    /** Wochenmenüplanstruktur */
    public const WEEKLY_MENU_PLAN_STRUCTURES = "weekly-menu-plan-structures";
    /** Wochenmenüplan Texte */
    public const WEEKLY_MENU_PLAN_TEXTS = "weekly-menu-plan-texts";
    /** Wochenmenüplan */
    public const WEEKLY_MENU_PLANS = "weekly-menu-plans";
    /** Arbeitsaufgabe Dokumente */
    public const WORK_TASK_DOCUMENTS = "work-task-documents";
    /** Arbeitsaufgabe */
    public const WORK_TASKS = "work-tasks";
    /** Workflowknoten Struktur Texte */
    public const WORKFLOW_NODE_STRUCTURE_TEXTS = "workflow-node-structure-texts";
    /** Workflowknoten Struktur */
    public const WORKFLOW_NODE_STRUCTURES = "workflow-node-structures";
    /** Workflowknoten Texte */
    public const WORKFLOW_NODE_TEXTS = "workflow-node-texts";
    /** Workflowknoten */
    public const WORKFLOW_NODES = "workflow-nodes";
    /** Workflowstatus */
    public const WORKFLOW_STATUS = "workflow-status";
    /** Workflowstatus Texte */
    public const WORKFLOW_STATUS_TEXTS = "workflow-status-texts";
    /** Workflow Texte */
    public const WORKFLOW_TEXTS = "workflow-texts";
    /** Workflow */
    public const WORKFLOWS = "workflows";

    /**
     * slug => ["label" => string, "filters" => string[]]
     *
     * @var array<string, array{label: string, filters: array<int, string>}>
     */
    public const REGISTRY = [
        "accounting-systems-interfaces" => ["label" => "Buchhaltungsschnittstelle", "filters" => []],
        "accruals" => ["label" => "Rückstellung", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "agencys" => ["label" => "Behörde", "filters" => ["invoiceStartdateFrom", "invoiceStartdateTo", "invoiceNextdateFrom", "invoiceNextdateTo", "paymentTypeValues"]],
        "assignments" => ["label" => "Zuordnungen", "filters" => ["supplierItemId", "productId", "lastOrderDateFrom", "lastOrderDateTo"]],
        "assortment-indexs" => ["label" => "Warengruppenindex", "filters" => ["assortmentId"]],
        "assortment-texts" => ["label" => "Warengruppentexte", "filters" => ["assortmentId", "languageId"]],
        "assortments" => ["label" => "Warengruppen", "filters" => []],
        "batch-number-corrections" => ["label" => "Chargennummerkorrektur", "filters" => ["productId", "dateCorrectionFrom", "dateCorrectionTo"]],
        "belt-post-group-texts" => ["label" => "Bandpostengruppe Texte", "filters" => ["beltPostGroupId", "languageId"]],
        "belt-post-groups" => ["label" => "Bandpostengruppe", "filters" => []],
        "belt-posts" => ["label" => "Bandposten", "filters" => ["beltPostGroupId"]],
        "business-events" => ["label" => "Aufgabe eines Geschäftsprozesses", "filters" => ["workTaskId", "creationDateFrom", "creationDateTo", "progressDateFrom", "progressDateTo", "closeDateFrom", "closeDateTo", "statusValues"]],
        "calendar-structure-texts" => ["label" => "Kalender Struktur Texte", "filters" => ["calendarStructureId", "languageId"]],
        "calendar-structures" => ["label" => "Kalender Struktur", "filters" => ["calendarId", "dateFromFrom", "dateFromTo", "dateUntilFrom", "dateUntilTo"]],
        "calendar-texts" => ["label" => "Kalender Texte", "filters" => ["calendarId", "languageId"]],
        "calendars" => ["label" => "Kalender", "filters" => ["validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "cash-register-group-texts" => ["label" => "Kassenwarengruppe Texte", "filters" => ["cashRegisterGroupId", "languageId"]],
        "cash-register-groups" => ["label" => "Kassenwarengruppe", "filters" => ["typeSValues"]],
        "cash-register-report-category-texts" => ["label" => "Kassenbericht Kategorie Texte", "filters" => ["cashRegisterReportCategoryId", "languageId"]],
        "cash-register-report-categorys" => ["label" => "Kassenbericht Kategorie", "filters" => ["typeValues"]],
        "cash-register-report-details" => ["label" => "Kassenbericht Details", "filters" => ["cashRegisterReportId"]],
        "cash-register-report-vouchers" => ["label" => "Kassenbericht Gutscheine", "filters" => ["cashRegisterReportId"]],
        "cash-register-reports" => ["label" => "Kassenbericht", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "cashier-interfaces" => ["label" => "Kassensystemschnittstelle", "filters" => []],
        "cashier-items" => ["label" => "Kassensystem Artikel", "filters" => ["typeValues"]],
        "catering-class" => ["label" => "Verpflegsklassen", "filters" => ["menuClassId"]],
        "catering-class-budget-structures" => ["label" => "Verpflegsbudget Struktur", "filters" => ["cateringClassesBudgetId"]],
        "catering-class-budgets" => ["label" => "Verpflegsbudget", "filters" => ["menuClassId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "catering-class-texts" => ["label" => "Verpflegsklassen Texte", "filters" => ["cateringClassId", "languageId"]],
        "catering-statistics" => ["label" => "Verpflegsstatistik", "filters" => ["menuClassId", "validOnDateFrom", "validOnDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "modusValues"]],
        "catering-statistics-historys" => ["label" => "Änderungshistorie der Verpflegsstandsmeldungen", "filters" => ["cateringStatisticId", "timestampFrom", "timestampTo", "modeValues"]],
        "catering-statistics-structures" => ["label" => "Verpflegsstatistik Struktur", "filters" => ["cateringStatisticId", "changeDateFrom", "changeDateTo"]],
        "catering-type-texts" => ["label" => "Verpflegsart Texte", "filters" => ["cateringTypeId", "languageId"]],
        "catering-types" => ["label" => "Verpflegsart", "filters" => ["cateringClassId"]],
        "certificate-texts" => ["label" => "Zertifikat Texte", "filters" => ["certificateId", "languageId"]],
        "certificates" => ["label" => "Zertifikat", "filters" => []],
        "classification-texts" => ["label" => "Klassifizierung Texte", "filters" => ["classificationId", "languageId"]],
        "classifications" => ["label" => "Klassifizierung", "filters" => []],
        "complaint-class" => ["label" => "Reklamationsklasse", "filters" => ["typeValues"]],
        "complaint-class-pictures" => ["label" => "Reklamationsklasse Bilder", "filters" => ["complaintId"]],
        "complaint-class-texts" => ["label" => "Reklamationsklasse Texte", "filters" => ["complaintClassId", "languageId"]],
        "complaints" => ["label" => "Feedback/Reklamationen", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "container-box-texts" => ["label" => "Transportbox Texte", "filters" => ["containerBoxId", "languageId"]],
        "container-boxs" => ["label" => "Transportbox", "filters" => []],
        "container-texts" => ["label" => "Behälter Texte", "filters" => ["containerId", "languageId"]],
        "containers" => ["label" => "Behälter", "filters" => []],
        "cost-center-class" => ["label" => "Objektklasse", "filters" => []],
        "cost-center-class-texts" => ["label" => "Objektklasse Texte", "filters" => ["costCenterClassId", "languageId"]],
        "cost-center-extenstions" => ["label" => "Kostenstellenerweiterungen", "filters" => ["costCenterId", "cashRegisterInterfaceLastSalesFigureImportFrom", "cashRegisterInterfaceLastSalesFigureImportTo", "cashRegisterInterfaceLastStockImportFrom", "cashRegisterInterfaceLastStockImportTo", "bookingSystemLastExpFrom", "bookingSystemLastExpTo", "bookingSystemInterfaceDateLastExportGoodsFrom", "bookingSystemInterfaceDateLastExportGoodsTo", "cashLastStartedFrom", "cashLastStartedTo", "cashVectronItemModeValues"]],
        "cost-center-links" => ["label" => "Kostenstellenverknüfpungen", "filters" => ["costCenterId", "invoiceStartdateFrom", "invoiceStartdateTo", "invoiceNextdateFrom", "invoiceNextdateTo", "isGrossNetValues", "invoiceModePrintValues"]],
        "cost-center-texts" => ["label" => "Kostenstellen Texte", "filters" => ["costCenterId", "languageId"]],
        "cost-centers" => ["label" => "Kostenstelle", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "isSubMasterValues", "vatFreeValues", "packagingTypeValues", "day1PackTypeValues", "day2PackTypeValues", "day3PackTypeValues", "day4PackTypeValues", "day5PackTypeValues", "day6PackTypeValues", "day7PackTypeValues"]],
        "country-texts" => ["label" => "Land Texte", "filters" => ["countryId", "languageId"]],
        "countrys" => ["label" => "Land", "filters" => []],
        "currencys" => ["label" => "Währung", "filters" => []],
        "customer-accounts" => ["label" => "Kunde Konto", "filters" => ["customerId", "bookingTiimeFrom", "bookingTiimeTo", "typeValues"]],
        "customer-bonus" => ["label" => "Kundenbonifiaktion", "filters" => ["customerId", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo"]],
        "customer-contact-allergens" => ["label" => "Kundenkontakt Allergene", "filters" => ["customerContactId", "checkedDateFrom", "checkedDateTo"]],
        "customer-contact-catering-days" => ["label" => "Kundenkontakt Verpflegstage", "filters" => ["customerContactId", "dateFromFrom", "dateFromTo", "dateUntilFrom", "dateUntilTo", "typeValues"]],
        "customer-contact-diet-profiles" => ["label" => "Kundenkontakt Diätologieprofile", "filters" => ["customerContactId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "customer-contact-historys" => ["label" => "Kundenkontakt Historie (für künftige Entwicklung)", "filters" => ["customerContactId", "dateFrom", "dateTo"]],
        "customer-contact-permissions" => ["label" => "Kundenkontakt Berechtigungen", "filters" => ["customerContactId"]],
        "customer-contact-subsidys" => ["label" => "Kundenkontakt  Förderungen", "filters" => ["customerContactId", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo"]],
        "customer-contacts" => ["label" => "Kundenkontakt", "filters" => ["customerId", "dateBirthFrom", "dateBirthTo", "creationDateFrom", "creationDateTo", "lastLoginFrom", "lastLoginTo", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "sepaDateFrom", "sepaDateTo", "orderTypeValues", "packagingTypeValues", "paymentTypeValues", "genderValues", "typeValues", "sepaStatusValues", "accountStatusValues"]],
        "customer-delivery-address" => ["label" => "Kunde Lieferadresse", "filters" => ["customerId"]],
        "customer-departments" => ["label" => "Kundenabteilung", "filters" => ["customerId", "packagingTypeValues"]],
        "customer-discounts" => ["label" => "Kunde Rabatt", "filters" => ["customerId", "discountArticleValues"]],
        "customer-documents" => ["label" => "Kunde Dokumente", "filters" => ["customerId", "dateFrom", "dateTo"]],
        "customer-interfaces" => ["label" => "Kunde Schnittstelle", "filters" => ["customerId", "interfaceTypeValues"]],
        "customers" => ["label" => "Kunde", "filters" => ["dateBirthFrom", "dateBirthTo", "creationDateFrom", "creationDateTo", "invoiceStartdateFrom", "invoiceStartdateTo", "invoiceNextdateFrom", "invoiceNextdateTo", "dunningBlockDateFrom", "dunningBlockDateTo", "deliveryBlockDateFrom", "deliveryBlockDateTo", "sepaDateFrom", "sepaDateTo", "paymentTypeValues", "vatFreeValues", "packagingTypeValues", "ftpImportFormatValues", "ftpExportFormatValues", "mediumOutDeliveryNotesValues", "formatOutDeliveryNotesValues", "mediumOutInvoiceValues", "formatOutInvoiceValues", "invoicePeriodValues", "invoicePeriodStartValues", "invoiceCreationbaseValues", "mediumCatalogValues", "formatCatalogValues", "day1PackTypeValues", "day2PackTypeValues", "day3PackTypeValues", "day4PackTypeValues", "day5PackTypeValues", "day6PackTypeValues", "day7PackTypeValues", "invoiceAccrualTypeValues", "invoiceModePrintValues", "mediumInInvoiceOrderValues", "formatInInvoiceOrderValues", "sepaStatusValues", "invoiceAccumModeValues", "accountStatusValues"]],
        "cutting-structures" => ["label" => "Zerlegung Struktur", "filters" => ["cuttingId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo"]],
        "cuttings" => ["label" => "Zerlegung", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "dashboard-texts" => ["label" => "Dashboard", "filters" => ["dashboardId", "languageId"]],
        "dashboard-user-groups" => ["label" => "Dashboard", "filters" => ["dashboardId"]],
        "dashboard-widget-texts" => ["label" => "Dashboard", "filters" => ["dashboardsWidgetId", "languageId"]],
        "dashboard-widgets" => ["label" => "Dashboard", "filters" => ["dashboardId"]],
        "dashboards" => ["label" => "Dashboard", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "delivery-products-packaging-relations" => ["label" => "Lieferprodukt Verpackungsreleation", "filters" => ["productId"]],
        "delivery-products-packaging-relations-texts" => ["label" => "Lieferprodukt Verpackungsreleation Texte", "filters" => ["supplierProductsPackagingRelationId", "languageId"]],
        "delivery-products-packaging-structures" => ["label" => "Lieferprodukt Verpackungsstruktur", "filters" => ["supplierProductsPackagingRelationId"]],
        "deposit-accounts" => ["label" => "Pfandkonto", "filters" => ["productId", "supplierId"]],
        "deposit-links" => ["label" => "Pfandverknüpfung", "filters" => ["supplierItemId", "productId"]],
        "diet-group-texts" => ["label" => "Kostgruppe Texte", "filters" => ["costGroupId", "languageId"]],
        "diet-groups" => ["label" => "Kostgruppe", "filters" => []],
        "diet-profile-texts" => ["label" => "Diätologieprofil Texte", "filters" => ["dietProfileId", "languageId"]],
        "diet-profiles" => ["label" => "Diätologieprofil", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "diet-type-texts" => ["label" => "Kostform Texte", "filters" => ["dietTypeId", "languageId"]],
        "diet-types" => ["label" => "Kostform", "filters" => []],
        "eeaternity-forecast-details" => ["label" => "Eeaternity Prognose Details", "filters" => ["productId", "eeaternityForecastId", "validDateFrom", "validDateTo"]],
        "eeaternity-forecast-info-messages" => ["label" => "Eeaternity Prognose Infomeldungen", "filters" => ["eeaternityForecastId", "messageTypeValues"]],
        "eeaternity-forecasts" => ["label" => "Eeaternity Prognose", "filters" => ["creationDateFrom", "creationDateTo", "lastUpdateFrom", "lastUpdateTo", "dateFromFrom", "dateFromTo", "dateToFrom", "dateToTo", "predStatusValues"]],
        "external-purchase-order-structures" => ["label" => "Externe Bestellung Struktur", "filters" => ["externalPurchaseOrderId"]],
        "external-purchase-orders" => ["label" => "Externe Bestellung", "filters" => ["customerId", "orderDateFrom", "orderDateTo", "deliveryDateFrom", "deliveryDateTo", "typeValues"]],
        "feature-usages" => ["label" => "Funktionsnutzung", "filters" => ["dateFrom", "dateTo"]],
        "fixed-cost-period-interruptions" => ["label" => "Fixkostenperiode Unterbrechungen", "filters" => ["fixedCostPeriodId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "fixed-cost-period-structures" => ["label" => "Fixkostenperiode Struktur", "filters" => ["fixedCostPeriodId", "monthlyYearlyValues"]],
        "fixed-cost-periods" => ["label" => "Fixkostenperiode", "filters" => ["validFromFrom", "validFromTo", "validToFrom", "validToTo", "budgetTypeValues"]],
        "flow-rates" => ["label" => "Durchflussmenge", "filters" => ["costCenterId", "dueDateFrom", "dueDateTo"]],
        "general-standard-cooking-programs" => ["label" => "Allgemeine Standardgarprogramme", "filters" => ["costCenterId"]],
        "goods-issue-structure-by-items" => ["label" => "Warenausgabe Struktur artikelbezogen", "filters" => ["goodsIssueStructureId"]],
        "goods-issue-structures" => ["label" => "Warenausgabe Struktur", "filters" => ["goodsIssueId"]],
        "goods-issues" => ["label" => "Warenausgabe", "filters" => ["transDateFrom", "transDateTo", "typeValues"]],
        "haccp-instruction-texts" => ["label" => "Haccp-Anweisungen Texte", "filters" => ["haccpInstructionId", "languageId"]],
        "haccp-instructions" => ["label" => "Haccp-Anweisungen", "filters" => []],
        "in-delivery-note-documents" => ["label" => "Eingangslieferschein Dokumente", "filters" => ["inDeliveryNoteId"]],
        "in-delivery-note-evaluation-criterias" => ["label" => "Lieferscheinbewertungskriterien", "filters" => ["paramTypeValues"]],
        "in-delivery-note-evaluation-structures" => ["label" => "Lieferscheinbewertung Struktur", "filters" => ["inDeliveryNoteId"]],
        "in-delivery-note-evaluation-texts" => ["label" => "Lieferscheinbewertung Texte", "filters" => ["deliveryNoteEvaluationCriteriaId", "languageId"]],
        "in-delivery-note-goods-receipt-report-definition-texts" => ["label" => "Eingangslieferschein Wareneingangsprotokoll Definition Texte", "filters" => ["inDeliveryNotesGoodsReceiptReportsDefinitionId", "languageId"]],
        "in-delivery-note-goods-receipt-report-definitions" => ["label" => "Eingangslieferschein Wareneingangsprotokoll Definition", "filters" => ["inDeliveryNotesGoodsReceiptReportId", "valTypeValues"]],
        "in-delivery-note-goods-receipt-report-texts" => ["label" => "Eingangslieferschein Wareneingangsprotokoll Texte", "filters" => ["inDeliveryNotesGoodsReceiptReportId", "languageId"]],
        "in-delivery-note-goods-receipt-report-triggers" => ["label" => "Eingangslieferschein Wareneingangsprotokoll Trigger", "filters" => ["inDeliveryNotesGoodsReceiptReportsDefinitionId"]],
        "in-delivery-note-goods-receipt-reports" => ["label" => "Eingangslieferschein Wareneingangsprotokoll", "filters" => []],
        "in-delivery-note-replacement-deliverys" => ["label" => "Eingangslieferschein Ersatzlieferungen", "filters" => ["inDeliveryNoteId"]],
        "in-delivery-note-structure-split-bookings" => ["label" => "Eingangslieferschein Struktur Splitbuchung", "filters" => ["inDeliveryNotesStructureId"]],
        "in-delivery-note-structures" => ["label" => "Eingangslieferschein Struktur", "filters" => ["inDeliveryNoteId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo", "discountItemTypeValues", "discountTypeProductClassDiscountValues"]],
        "in-delivery-notes" => ["label" => "Eingangslieferschein", "filters" => ["accountingDateFrom", "accountingDateTo", "paymentDateFrom", "paymentDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "exportDateFrom", "exportDateTo", "statusValues"]],
        "in-invoice-account-assignments" => ["label" => "Eingangsrechnung Kontierung", "filters" => ["inInvoiceId", "rowTypeValues"]],
        "in-invoice-documents" => ["label" => "Eingangsrechnung Dokumente", "filters" => ["inInvoiceId"]],
        "in-invoice-structure-cost-center-splits" => ["label" => "Eingangsrechnung Struktur Kostenstellensplittung", "filters" => ["inInvoicesStructureId"]],
        "in-invoice-structures" => ["label" => "Eingangsrechnung Struktur", "filters" => ["inInvoiceId", "deliveryDateFrom", "deliveryDateTo", "orderDateFrom", "orderDateTo", "discountItemTypeValues", "discountTypeProductClassDiscountValues", "priceStatusValues", "sourceTypeValues"]],
        "in-invoices" => ["label" => "Eingangsrechnung", "filters" => ["accountingDateFrom", "accountingDateTo", "dueDateFrom", "dueDateTo", "paymentDateFrom", "paymentDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "exportDateFrom", "exportDateTo", "servicePeriodStartDateFrom", "servicePeriodStartDateTo", "performancePeriodEndDateFrom", "performancePeriodEndDateTo", "importDateFrom", "importDateTo", "releasedDateFrom", "releasedDateTo", "checkedDateFrom", "checkedDateTo"]],
        "inventory-balance-by-items" => ["label" => "Inventurstand artikelbezogen", "filters" => ["supplierItemId", "productId", "deadlineFrom", "deadlineTo"]],
        "inventory-balances" => ["label" => "Inventurstand", "filters" => ["stockId", "deadlineFrom", "deadlineTo", "batchBestBeforeDateFrom", "batchBestBeforeDateTo"]],
        "inventory-closing-stock-valuations" => ["label" => "Inventurabschluss Lagerbewertung", "filters" => ["productId"]],
        "inventory-movement-notes" => ["label" => "Lagerbewegungsnotiz", "filters" => []],
        "inventory-movements" => ["label" => "Lagerbewegungen", "filters" => ["stockId", "deadlineFrom", "deadlineTo", "typeValues"]],
        "inventory-period-closings" => ["label" => "Inventurabschluss/Periodenabschluss", "filters" => ["costCenterId", "closingDateFrom", "closingDateTo", "typeValues"]],
        "languages" => ["label" => "Sprache", "filters" => []],
        "list-label-layouts" => ["label" => "List&Label Layout", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "outputFormatValues", "typeSystemValues"]],
        "lmiv-key-tests" => ["label" => "LMIV Key Test", "filters" => ["lmivKeyId"]],
        "lmiv-keys" => ["label" => "LMIV Key", "filters" => []],
        "lmiv-keywords" => ["label" => "LMIV Keyword", "filters" => ["lmivKeyId", "separatorTypeValues"]],
        "machine-code-texts" => ["label" => "Maschinencode Texte", "filters" => ["machinecodeId", "languageId"]],
        "machine-codes" => ["label" => "Maschinencode", "filters" => []],
        "mbs-menu-order-profile-structures" => ["label" => "MBS Menübestellprofil Struktur", "filters" => ["mbsMenuOrderProfileId"]],
        "mbs-menu-order-profiles" => ["label" => "MBS Menübestellprofil", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "isCustomerDepartmentOrderProfileValues"]],
        "meal-texts" => ["label" => "Mahlzeit Texte", "filters" => ["mealId", "languageId"]],
        "meals" => ["label" => "Mahlzeit", "filters" => ["mealTypeValues"]],
        "menu-class" => ["label" => "Menüplanklasse", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "preOrderPeriodValues", "preOrderTypeLeadDaysValues", "reservationTypeLeadDaysValues", "cancellationTypeLeadDaysValues", "cateringStatisticNoteLeadTimeTypeValues"]],
        "menu-class-budget-details" => ["label" => "Menüplanklassenbudget Details", "filters" => ["menuClassBudgetId"]],
        "menu-class-budgets" => ["label" => "Menüplanklassenbudget", "filters" => ["menuClassId", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "isGrossNetValues"]],
        "menu-class-prices" => ["label" => "Menüplanpreise (obsolet)", "filters" => ["menuClassBudgetId"]],
        "menu-class-texts" => ["label" => "Menüplanklassen Texte", "filters" => ["menuClassId", "languageId"]],
        "menu-plan-prices" => ["label" => "Menüplanpreise", "filters" => ["menuClassBudgetId", "priceBaseValues", "adjustmentValues", "roundingModeValues"]],
        "menu-plan-prices-step-functions" => ["label" => "Menüplanpreise Stufenfunktion", "filters" => ["menuPlanPriceId"]],
        "menu-plan-profile-budgets" => ["label" => "Menüplanprofilbudget", "filters" => ["menuPlanProfileId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "menu-plan-profile-connections" => ["label" => "Menüplanprofil Verknüfpungen", "filters" => ["menuPlanProfileId"]],
        "menu-plan-profile-deadline-meals" => ["label" => "Menüplanprofil Deadline Mahlzeit", "filters" => ["preOrderPeriodValues", "preOrderTypeLeadDaysValues", "cancellationTypeLeadDaysValues"]],
        "menu-plan-profile-delivery-time-texts" => ["label" => "Menüplanprofil Lieferzeiten", "filters" => ["menuPlanProfilesDeliveryTimeId", "languageId"]],
        "menu-plan-profile-delivery-times" => ["label" => "Menüplanprofil Lieferzeiten", "filters" => ["menuPlanProfileId"]],
        "menu-plan-profile-texts" => ["label" => "Menüplanprofil Texte", "filters" => ["menuPlanProfileId", "languageId"]],
        "menu-plan-profiles" => ["label" => "Menüplanprofil", "filters" => ["menuClassId", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "preOrderPeriodValues", "preOrderTypeLeadDaysValues", "reservationTypeAdvanceDaysValues", "cancellationTypeLeadDaysValues"]],
        "menu-pre-order-additional-orders" => ["label" => "Menüvorbestellung Zusatzbestellung", "filters" => ["menuPreOrderId"]],
        "menu-pre-order-notes" => ["label" => "Menüvorbestellung Notiz", "filters" => ["menuPreOrderId"]],
        "menu-pre-order-order-requisitions" => ["label" => "Menüvorbestellung Bestellanforderung", "filters" => ["menuPreOrdersStructureId"]],
        "menu-pre-order-structures" => ["label" => "Menüvorbestellung Struktur", "filters" => ["menuPreOrderId", "packagingTypeValues"]],
        "menu-pre-orders" => ["label" => "Menüvorbestellung", "filters" => ["weekMenuPlanId"]],
        "menu-preordering-distribution-keys" => ["label" => "Menüvorbestllung Verteilungsschlüssel", "filters" => ["cateringTypeId"]],
        "menu-quantities" => ["label" => "Menüplanmengen (Obsolet)", "filters" => ["weekMenuPlanId"]],
        "menue-plan-profil-budget-structures" => ["label" => "Menüplanprofilbudget Struktur", "filters" => ["menueplanprofilbudgetId"]],
        "message-recipients" => ["label" => "Nachrichtenempfänger", "filters" => ["messageId"]],
        "messages" => ["label" => "Nachricht", "filters" => ["creationDateFrom", "creationDateTo", "typeSValues"]],
        "microbacteriological-evaluation-structure-texts" => ["label" => "Microbakteorologische Bewertung Struktur Texte", "filters" => ["microbacteriologicalEvaluationsStructureId", "languageId"]],
        "microbacteriological-evaluation-structures" => ["label" => "Microbakteorologische Bewertung Struktur", "filters" => ["microbacteriologicalEvaluationId"]],
        "microbacteriological-evaluation-texts" => ["label" => "Microbakteorologische Bewertung Texte", "filters" => ["microbacteriologicalEvaluationId", "languageId"]],
        "microbacteriological-evaluations" => ["label" => "Microbakteorologische Bewertung", "filters" => []],
        "necta-attribute-texts" => ["label" => "Attribut Texte", "filters" => ["nectaAttributeId", "languageId"]],
        "necta-attributes" => ["label" => "Attribut", "filters" => ["calcTypeValues", "validTypeValues", "classTypeValues", "systemTypeValues"]],
        "nutrient-class" => ["label" => "Nährwertklasse", "filters" => []],
        "nutrient-class-texts" => ["label" => "Nährwertklasse Texte", "filters" => ["nutrientClassId", "languageId"]],
        "nutrient-group-structures" => ["label" => "Nährwertgruppe Struktur", "filters" => ["nutrientGroupId"]],
        "nutrient-group-texts" => ["label" => "Nährwertgruppe Texte", "filters" => ["nutrientGroupId", "languageId"]],
        "nutrient-groups" => ["label" => "Nährwertgruppe", "filters" => []],
        "nutrient-recommendations" => ["label" => "Nährwertempfehlungen", "filters" => []],
        "nutrient-texts" => ["label" => "Nährwert Texte", "filters" => ["nutrientId", "languageId"]],
        "nutrients" => ["label" => "Nährwert", "filters" => []],
        "nuts-region-texts" => ["label" => "NUTS Region Texte", "filters" => ["nutsRegionId", "languageId"]],
        "nuts-regions" => ["label" => "NUTS Region", "filters" => []],
        "object-class-assignments" => ["label" => "Objektklassenzuordnungen", "filters" => ["costCenterClassId", "supplierItemId", "productId"]],
        "object-class-assignments-cost-center-specifics" => ["label" => "Objektklassenzuordnungen Kostenstellenspezifisch", "filters" => ["objectClassAssignmentId"]],
        "optimix-areas" => ["label" => "Optimix Bereich", "filters" => ["optimixDefinitionId"]],
        "optimix-definitions" => ["label" => "Optimixdefinitonen", "filters" => ["genderValues"]],
        "optimix-types" => ["label" => "Optimix Typ", "filters" => []],
        "order-requisition-texts" => ["label" => "Bestellanforderung Texte", "filters" => ["purchaseRequestId", "languageId"]],
        "order-requisitions" => ["label" => "Bestellanforderung", "filters" => ["typeValues"]],
        "order-structure-order-requisitions" => ["label" => "Auftrag Struktur Bestellanforderungen", "filters" => ["ordersStructureId"]],
        "order-structures" => ["label" => "Auftrag Struktur", "filters" => ["orderId", "deliveryDateFrom", "deliveryDateTo", "packagingTypeValues"]],
        "orders" => ["label" => "Auftrag", "filters" => ["dateFrom", "dateTo", "deliveryDateFrom", "deliveryDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "sentDateFrom", "sentDateTo", "exportDateFrom", "exportDateTo", "statusValues", "isGrossNetValues", "packagingTypeValues", "packTypeValues", "paymentTypeValues", "typeValues"]],
        "out-delivery-note-documents" => ["label" => "Ausgangslieferschein Dokumente", "filters" => []],
        "out-delivery-note-structures" => ["label" => "Ausgangslieferschein Struktur", "filters" => ["outDeliveryNoteId", "sourceTypeValues"]],
        "out-delivery-notes" => ["label" => "Ausgangslieferschein", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "sentDateFrom", "sentDateTo", "statusValues", "isGrossNetValues", "dedefleetStatusValues"]],
        "out-invoice-structures" => ["label" => "Ausgangsrechnung Struktur", "filters" => ["outInvoiceId", "sourceTypeValues"]],
        "out-invoices" => ["label" => "Ausgangsrechnung", "filters" => ["dateFrom", "dateTo", "paymentDateFrom", "paymentDateTo", "zahlungszielFrom", "zahlungszielTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "servicePeriodStartDateFrom", "servicePeriodStartDateTo", "performancePeriodEndDateFrom", "performancePeriodEndDateTo", "sentDateFrom", "sentDateTo", "exportDateFrom", "exportDateTo", "prepaidBenefitPeriodStartFrom", "prepaidBenefitPeriodStartTo", "prepaidBenefitPeriodEndFrom", "prepaidBenefitPeriodEndTo", "sentDateDunningLevel1From", "sentDateDunningLevel1To", "sentDateDunningLevel2From", "sentDateDunningLevel2To", "sentDateDunningLevel3From", "sentDateDunningLevel3To", "paymentTypeValues", "statusValues", "isGrossNetValues", "vatTypeValues"]],
        "out-invoices-account-assignments" => ["label" => "Ausgangsrechnung Kontierung", "filters" => ["outInvoiceId"]],
        "packaging-consumptions" => ["label" => "Verpackung Vebrauch", "filters" => ["packagingId", "stockDateFrom", "stockDateTo"]],
        "packaging-products" => ["label" => "Verpackung Produkte", "filters" => ["packagingId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo"]],
        "packagings" => ["label" => "Verpackung", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "packing-station-texts" => ["label" => "Verpackstation Texte", "filters" => ["packingStationId", "languageId"]],
        "packing-stations" => ["label" => "Verpackstation", "filters" => []],
        "partial-production-consumptions" => ["label" => "Teilproduktion Verbrauch", "filters" => ["partialProductionId"]],
        "partial-productions" => ["label" => "Teilproduktion", "filters" => ["productionId", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "payments" => ["label" => "Zahlung", "filters" => ["timestampCreationFrom", "timestampCreationTo", "timestampConfirmationFrom", "timestampConfirmationTo", "statusValues"]],
        "picking-list-structures" => ["label" => "Kommissionierliste Struktur", "filters" => ["pickingListId"]],
        "picking-lists" => ["label" => "Kommissionierliste", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "pre-order-quantitys" => ["label" => "Vorbestellmengen", "filters" => ["productionId"]],
        "price-quarantine-structures" => ["label" => "Preisquarantäne Struktur", "filters" => ["priceQuarantineId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "price-quarantines" => ["label" => "Preisquarantäne", "filters" => ["dateImportedFrom", "dateImportedTo", "dateAppliedFrom", "dateAppliedTo", "statusValues"]],
        "product-allergens" => ["label" => "Produkt Allergene", "filters" => ["productId", "checkedDateFrom", "checkedDateTo"]],
        "product-area-released-for-other-tenants" => ["label" => "Freigabe Produktbereich für anderen Mandanten", "filters" => ["productAreaId", "changeDateFrom", "changeDateTo"]],
        "product-area-texts" => ["label" => "Produktbereich Texte", "filters" => ["productAreaId", "languageId"]],
        "product-areas" => ["label" => "Produktbereich", "filters" => []],
        "product-calculations" => ["label" => "Produkt Kalkulation", "filters" => ["productId", "calcDateFrom", "calcDateTo", "marketPriceDateFrom", "marketPriceDateTo", "isSurchargeStandardPriceLevel1Values", "isSurchargeStandardPriceLevel2Values", "isSupplementStandardPriceLevel3Values", "calcModeValues", "calculationModeBaseValuePriceLevel2Values", "calculationModeBaseValuePriceLevel3Values", "calculationModeBaseValuePriceLevel4Values", "calculationModeBaseValuePriceLevel5Values", "calculationModeBaseValuePriceLevel6Values", "calculationModeBaseValuePriceLevel7Values", "calculationModeBaseValuePriceLevel8Values", "calculationModeBaseValuePriceLevel9Values", "calculationModeBaseValuePriceLevel10Values", "calculationModeBaseValuePriceLevel11Values", "calculationModeBaseValuePriceLevel12Values", "priceAdjustmentPriceLevel1Values", "priceAdjustmentPriceLevel2Values", "priceAdjustmentPriceLevel3Values", "priceAdjustmentPriceLevel4Values", "priceAdjustmentPriceLevel5Values", "priceAdjustmentPriceLevel6Values", "priceAdjustmentPriceLevel7Values", "priceAdjustmentPriceLevel8Values", "priceAdjustmentPriceLevel9Values", "priceAdjustmentPriceLevel10Values", "priceAdjustmentPriceLevel11Values", "priceAdjustmentPriceLevel12Values", "surcharge01Default2Values", "surcharge02Default2Values", "surcharge03Default2Values", "surcharge01Default3Values", "surcharge02Default3Values", "surcharge03Default3Values", "surcharge01Default4Values", "surcharge02Default4Values", "surcharge03Default4Values", "surcharge01Default5Values", "surcharge02Default5Values", "surcharge03Default5Values", "surcharge01Default6Values", "surcharge02Default6Values", "surcharge03Default6Values", "surcharge01Default7Values", "surcharge02Default7Values", "surcharge03Default7Values", "surcharge01Default8Values", "surcharge02Default8Values", "surcharge03Default8Values", "surcharge01Default9Values", "surcharge02Default9Values", "surcharge03Default9Values", "surcharge01Default10Values", "surcharge02Default10Values", "surcharge03Default10Values", "surcharge01Default11Values", "surcharge02Default11Values", "surcharge03Default11Values", "surcharge01Default12Values", "surcharge02Default12Values", "surcharge03Default12Values"]],
        "product-change-historys" => ["label" => "Änderungshitorie Produkte", "filters" => ["productId", "timestampFrom", "timestampTo", "modeValues"]],
        "product-class" => ["label" => "Produktklasse", "filters" => ["typeValues"]],
        "product-class-tax-archive-details" => ["label" => "Produktklassensteuerarchiv Details", "filters" => ["productTaxClassArchivId"]],
        "product-class-tax-archivs" => ["label" => "Produktklassensteuerarchiv", "filters" => ["costCenterId", "timestampFrom", "timestampTo"]],
        "product-class-texts" => ["label" => "Produktklasse Texte", "filters" => ["productClassId", "languageId"]],
        "product-classifications" => ["label" => "Produktklassifizierung", "filters" => ["productId"]],
        "product-clause-texts" => ["label" => "Produktklausel Texte", "filters" => ["productClauseId", "languageId"]],
        "product-clauses" => ["label" => "Produktklausel", "filters" => ["typeValues"]],
        "product-cost-center-specific-values" => ["label" => "Produkt kostenstellespezifische Werte", "filters" => ["productId"]],
        "product-declaration-substances" => ["label" => "Produkt Deklarationspflichtige Stoffe", "filters" => ["productId", "checkedDateFrom", "checkedDateTo"]],
        "product-documents" => ["label" => "Produkt Dokumente", "filters" => ["productId", "dateFrom", "dateTo"]],
        "product-eans" => ["label" => "Produkt EAN", "filters" => ["productId"]],
        "product-images" => ["label" => "Produktbilder", "filters" => ["productId"]],
        "product-ingredient-list-texts" => ["label" => "Produkt Zutatenliste Texte", "filters" => ["productIngredientListId", "languageId"]],
        "product-ingredient-lists" => ["label" => "Produkt Zutatenliste", "filters" => ["productId"]],
        "product-labels" => ["label" => "Produktlabels", "filters" => ["productionId", "createdFrom", "createdTo"]],
        "product-nutritional-values" => ["label" => "Produkt Nährwerte", "filters" => ["productId", "calcDateFrom", "calcDateTo"]],
        "product-optimix-criterias" => ["label" => "Produkt Optimixkriterien", "filters" => ["productId", "checkedDateFrom", "checkedDateTo", "lastCalculationDateFrom", "lastCalculationDateTo"]],
        "product-origin-cost-center-specifics" => ["label" => "Produkt Herkunft kostenstellenspezfisich", "filters" => ["productId"]],
        "product-price-historys" => ["label" => "Produktpreishistorie", "filters" => ["costCenterId", "deadlineFrom", "deadlineTo"]],
        "product-specification-texts" => ["label" => "Produkt Spezifikation Texte", "filters" => ["productSpecificationId", "languageId"]],
        "product-specifications" => ["label" => "Produkt Spezifikation", "filters" => ["productId", "dateFrom", "dateTo", "minTimeCalculationFrom", "minTimeCalculationTo", "eaternityTimeCalculationFrom", "eaternityTimeCalculationTo", "eaternityTimeNextRequestFrom", "eaternityTimeNextRequestTo", "eaternityStatusValues", "convenienceLevelValues"]],
        "product-ssccs" => ["label" => "Produkt SSCC", "filters" => ["productId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo", "dateFrom", "dateTo"]],
        "product-structure-texts" => ["label" => "Produkt Struktur Texte", "filters" => ["productStructureId", "languageId"]],
        "product-structures" => ["label" => "Produkt Struktur", "filters" => ["productId"]],
        "product-texts" => ["label" => "Produkt Texte", "filters" => ["productId", "languageId"]],
        "product-unit-relations" => ["label" => "Produkt Relationen", "filters" => ["productId"]],
        "product-videos" => ["label" => "Produktvideos", "filters" => ["productId"]],
        "production-consumption-groups" => ["label" => "Produktion Verbrauch Gruppe", "filters" => ["productionId"]],
        "production-consumptions" => ["label" => "Produktion Verbrauch", "filters" => ["productionId", "stockDateFrom", "stockDateTo"]],
        "production-container-box-demands" => ["label" => "Produktion Transportboxenbedarf", "filters" => ["productionId", "createdFrom", "createdTo"]],
        "production-cooking-programs" => ["label" => "Produktion Kochprogramme", "filters" => ["productionId"]],
        "production-distributions" => ["label" => "Produktion - Verteilung", "filters" => ["productionStructureId"]],
        "production-group-texts" => ["label" => "Produktionsgruppe Texte", "filters" => ["productionGroupId", "languageId"]],
        "production-groups" => ["label" => "Produktionsgruppe", "filters" => []],
        "production-line-texts" => ["label" => "Produktionslinie Texte", "filters" => ["productionLineId", "languageId"]],
        "production-lines" => ["label" => "Produktionslinie", "filters" => ["preOrderPeriodValues", "preOrderTypeLeadDaysValues"]],
        "production-products" => ["label" => "Produktion Produkte", "filters" => ["productionId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo"]],
        "production-reconciliation-structures" => ["label" => "Produtkionsabgleich Struktur", "filters" => ["productionReconciliationId"]],
        "production-reconciliations" => ["label" => "Produtkionsabgleich", "filters" => ["productDateFrom", "productDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "production-site-texts" => ["label" => "Produktionsort Texte", "filters" => ["productionSiteId", "languageId"]],
        "production-sites" => ["label" => "Produktionsort", "filters" => []],
        "production-structures" => ["label" => "Produktion Struktur", "filters" => ["productionId", "batchBestBeforeDateFrom", "batchBestBeforeDateTo", "checkTimestampFrom", "checkTimestampTo", "cookprgmTransferSValues"]],
        "productions" => ["label" => "Produktion", "filters" => ["productDateFrom", "productDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "markEordAmountsTimestampFrom", "markEordAmountsTimestampTo", "lastCpTransferDateFrom", "lastCpTransferDateTo", "mbsExportDatetimeFrom", "mbsExportDatetimeTo", "jackExportDatetimeFrom", "jackExportDatetimeTo", "statusValues"]],
        "products" => ["label" => "Produkt", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "validUntilFrom", "validUntilTo", "availableCheckDateFrom", "availableCheckDateTo", "orderSuspendFromFrom", "orderSuspendFromTo", "bestArticleValues", "productItemValues", "isPlasterLossGrossNetValues", "packTypeValues", "chargeTypeValues", "declarationClassValues"]],
        "purchase-order-product-related-demands" => ["label" => "Bestellung produktbezogener Bedarf", "filters" => ["purchaseOrdersStructureId"]],
        "purchase-order-reference-status-historys" => ["label" => "Bestellung Bestellreferenz (Summen/Status) Statushistorie", "filters" => ["purchaseOrdersOrderReferenceId", "entryDateFrom", "entryDateTo", "statusFromValues", "statusToValues"]],
        "purchase-order-references" => ["label" => "Bestellung Bestellreferenz (Summen/Status)", "filters" => ["purchaseOrderId", "deliveryDateFrom", "deliveryDateTo", "sentTimestampFrom", "sentTimestampTo", "statusValues"]],
        "purchase-order-structures" => ["label" => "Bestellung Struktur", "filters" => ["purchaseOrderId", "deliveryDateFrom", "deliveryDateTo", "originalDeliveryDateFrom", "originalDeliveryDateTo"]],
        "purchase-orders" => ["label" => "Bestellung", "filters" => ["orderDateFrom", "orderDateTo", "deliveryDateFrom", "deliveryDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "dateShipmentFrom", "dateShipmentTo", "exportDateFrom", "exportDateTo", "requirementDateFrom", "requirementDateTo", "statusValues", "orderTypeValues", "assortmentIdentifierValues"]],
        "reduced-menu-plan-profile-texts" => ["label" => "Reduziertes Menüplanprofil Texte", "filters" => ["reducedMenuPlanProfileId", "languageId"]],
        "reduced-menu-plan-profiles" => ["label" => "Reduziertes Menüplanprofil", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "rel-accrual2-in-delivery-notes" => ["label" => "Relation Rückstellung - Eingangslieferschein", "filters" => ["accrualId"]],
        "rel-belt-post-group2-cost-centers" => ["label" => "Relation Bandpostengruppe -  Kostenstelle", "filters" => ["beltPostGroupId", "costCenterId", "isEditableValues"]],
        "rel-belt-post-group2-product-class" => ["label" => "Relation Bandposten - Externe Produktklasse", "filters" => ["beltPostId", "productClassId"]],
        "rel-calendar2-cost-centers" => ["label" => "Relation Kalender -  Kostenstelle", "filters" => ["calendarId", "isEditableValues"]],
        "rel-catering-type2-cost-centers" => ["label" => "Relation Verpflegsart -  Kostenstelle", "filters" => ["cateringTypeId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "rel-classification2-customers" => ["label" => "Relation Klassifizierung -  Kunde", "filters" => ["customerId"]],
        "rel-classification2-suppliers" => ["label" => "Relation Klassifizierung -  Lieferant", "filters" => ["classificationId", "supplierId"]],
        "rel-container-box2-containers" => ["label" => "Relation Transportbox - Behälter", "filters" => ["containerBoxId"]],
        "rel-cost-center-class2-cost-centers" => ["label" => "Relation Objektklasse -  Kostenstelle", "filters" => ["costCenterId", "isEditableValues"]],
        "rel-customer-contact2-necta-attributes" => ["label" => "Relation Kundenkontakt - Attribute", "filters" => ["customerContactId"]],
        "rel-customer-delivery-address2-production-lines" => ["label" => "Relation Kundenlieferadresse - Produktionslinie", "filters" => ["customerDeliveryAddressId", "preOrderPeriodValues", "preOrderTypeLeadDaysValues"]],
        "rel-customer2-cost-centers" => ["label" => "Relation Kunde - Kostenstelle", "filters" => ["customerId", "isEditableValues"]],
        "rel-diet-profile2-diet-types" => ["label" => "Relation Diätologieprofil - Kostformen", "filters" => ["dietProfileId"]],
        "rel-in-delivery-note2-purchase-orders" => ["label" => "Relation Eingangslieferschein - Bestellung", "filters" => ["inDeliveryNoteId"]],
        "rel-in-invoice2-in-delivery-notes" => ["label" => "Relation Eingangsrechnung - Eingangslieferschein", "filters" => ["inInvoiceId"]],
        "rel-list-label-layout2-cost-centers" => ["label" => "Relation List&Label Layout - Kostenstelle", "filters" => ["listLabelLayoutId", "isEditableValues"]],
        "rel-menu-class2-cost-centers" => ["label" => "Relation Menüplanklasse -  Kostenstellen", "filters" => ["menuClassId", "isEditableValues"]],
        "rel-menu-class2-customers" => ["label" => "Relationen Menüplanklassen - Kunden", "filters" => ["menuClassId"]],
        "rel-menu-plan-profile2-catering-types" => ["label" => "Relation Menüplanprofil - Verpflegsart", "filters" => ["menuPlanProfileId", "packagingTypeValues"]],
        "rel-menu-plan-profile2-order-requisitions" => ["label" => "Relation Menüplanprofil - Bestellanforderung", "filters" => ["menuPlanProfileId"]],
        "rel-menu-plan-profile2-product-class" => ["label" => "Relation Menüplanprofil - Produktklasse", "filters" => ["menuPlanProfileId"]],
        "rel-menu-pre-order2-orders" => ["label" => "Relation Wochenmenüvorbestellung -  Auftrag", "filters" => ["menuPreOrderId"]],
        "rel-necta-attribute2-cost-centers" => ["label" => "Relation Attribut -  Kostenstelle", "filters" => ["nectaAttributeId", "isEditableValues"]],
        "rel-out-delivery-note2-order-structures" => ["label" => "Relation Ausgangslieferschen - Auftragszeile", "filters" => ["outDeliveryNoteId"]],
        "rel-out-delivery-note2-orders" => ["label" => "Relation Ausgangslieferschen - Auftrag", "filters" => ["outDeliveryNoteId"]],
        "rel-out-invoice2-in-delivery-notes" => ["label" => "Relation Ausgangsrechnung - Eingangslieferschein", "filters" => ["outInvoiceId"]],
        "rel-out-invoice2-order2-out-delivery-notes" => ["label" => "Relation Ausgangsrechnung - Auftrag/Ausgangslieferschein", "filters" => ["outInvoiceId"]],
        "rel-partial-production2-productions" => ["label" => "Relation Teilproduktion - Produktion", "filters" => ["productionStructureId"]],
        "rel-picking-list2-out-delivery-notes" => ["label" => "Relation Kommissionierliste - Ausgangslieferschein", "filters" => ["pickingListId"]],
        "rel-product-area-cost-centers" => ["label" => "Relation Produktbereich -  Kostenstelle", "filters" => ["productAreaId", "isEditableValues"]],
        "rel-product-class2-cost-centers" => ["label" => "Relation Produktklasse -  Kostenstelle ", "filters" => ["productClassId"]],
        "rel-product-class2-tenants" => ["label" => "Relation Produktklasse -  Kostenstelle ", "filters" => ["productClassId"]],
        "rel-product2-containers" => ["label" => "Reation Produkt - Container", "filters" => ["productId"]],
        "rel-product2-cost-center2-stocks" => ["label" => "Relation Produkt - Kostenstelle - Lager", "filters" => ["productId"]],
        "rel-product2-diet-types" => ["label" => "Relation Kostform - Produkt", "filters" => ["productId"]],
        "rel-product2-haccp-instructions" => ["label" => "Relation Produkt - HACCP Anweisung", "filters" => ["productId"]],
        "rel-product2-necta-attributes" => ["label" => "Relation Produkt - Attribut", "filters" => ["productId"]],
        "rel-product2-product-clauses" => ["label" => "Relation Produkt - Produktklausel", "filters" => ["productId"]],
        "rel-production-site2-cost-centers" => ["label" => "Relation Produktionsort - Kostenstelle", "filters" => ["productionSiteId", "isEditableValues"]],
        "rel-production-structure2-staff-members" => ["label" => "Relation Produktion Struktur Personalie", "filters" => ["productionStructureId"]],
        "rel-production2-orders" => ["label" => "Releation Produktion - Auftrag", "filters" => ["productionId"]],
        "rel-purchase-order-structure2-cost-centers" => ["label" => "Relation Musterbestellungsposition - Kostenstelle", "filters" => ["purchaseOrdersStructureId"]],
        "rel-purchase-order2-cost-centers" => ["label" => "Gültigkeit Bestellung - Kostenstelle", "filters" => ["purchaseOrderId", "isEditableValues"]],
        "rel-reduced-menu-plan-profile2-catering-types" => ["label" => "Relation Reduziertes Menüplanprofil - Verpflegsarten", "filters" => ["reducedMenuPlanProfileId"]],
        "rel-reduced-menu-plan-profile2-customer-contacts" => ["label" => "Reduziertes Menüplanprofil Kundenkontakt", "filters" => ["reducedMenuPlanProfielId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "rel-sales-list2-cost-centers" => ["label" => "Relation Verkaufsliste - Kostenstelle", "filters" => ["salesListId", "isEditableValues"]],
        "rel-sales-list2-customers" => ["label" => "Relation Verkaufsliste - Kunde", "filters" => ["salesListId"]],
        "rel-sales-list2-production-lines" => ["label" => "Relation Verkaufsliste - Produktionslinie", "filters" => ["salesListId"]],
        "rel-stock2-products" => ["label" => "Relation Lager -  Produkt", "filters" => ["productId"]],
        "rel-supplier-item2-cost-centers" => ["label" => "Relation Artikel - Kostenstelle", "filters" => ["costCenterId", "supplierItemId"]],
        "rel-supplier-item2-necta-attributes" => ["label" => "Mandatenspezifische Relation Lieferantenartikel - Attribut", "filters" => []],
        "rel-supplier-item2-product-class" => ["label" => "Relation Lieferantenartikel - necta Produktklassenbaum", "filters" => ["supplierItemId"]],
        "rel-supplier2-cost-centers" => ["label" => "Relation Lieferant - Kostenstelle", "filters" => ["costCenterId", "supplierId", "isEditableValues"]],
        "rel-supplier2-tenants" => ["label" => "Relation Lieferant - Mandant", "filters" => ["supplierId", "dateFrom", "dateTo", "lastLmivKeyCheckFrom", "lastLmivKeyCheckTo", "exportLevelValues"]],
        "rel-surcharge-class2-cost-centers" => ["label" => "Relation Aufschlagsklasse -  Kostenstelle", "filters" => ["surchargeClassId", "isEditableValues"]],
        "rel-tendering-supplier2-product-class" => ["label" => "Relation Auschreibung Lieferanten - Produktklasse", "filters" => ["tenderingSupplierId"]],
        "rel-tendering2-cost-center-class" => ["label" => "Relation Ausschreibung - Objektklasse", "filters" => ["tenderingId"]],
        "rel-tour2-customers" => ["label" => "Relation Tour - Kunde", "filters" => ["tourId"]],
        "rel-user-def2-cost-centers" => ["label" => "Relation User - Kostenstelle", "filters" => ["userDefId"]],
        "rel-user-def2-customers" => ["label" => "Relation User - Kunde", "filters" => ["userDefId"]],
        "rel-user-def2-report2-cost-centers" => ["label" => "Relation User -Report - Kostenstelle", "filters" => ["user2fReport2CostCenterId"]],
        "rel-week-production2-productions" => ["label" => "Relation Wochenproduktion - Produktion", "filters" => ["weekProductionId"]],
        "rel-weekly-menu-plan2-cost-centers" => ["label" => "Relation Wochenmenüplan -  Kostenstelle", "filters" => ["weekMenuPlanId", "isEditableValues"]],
        "report-groups" => ["label" => "Berichtsgruppe", "filters" => ["startDateFrom", "startDateTo", "periodValues"]],
        "report-template-texts" => ["label" => "Reportemplate Texte", "filters" => ["reportTemplateId", "languageId"]],
        "report-templates" => ["label" => "Reportemplate", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "reports" => ["label" => "Report", "filters" => ["userDefId", "toStartFrom", "toStartTo", "createdFrom", "createdTo", "periodStartFrom", "periodStartTo", "periodEndFrom", "periodEndTo", "readTimestampFrom", "readTimestampTo"]],
        "sales-list-promotion-periods" => ["label" => "Verkaufsliste Aktionszeitraum", "filters" => ["salesListId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "sales-list-structure-min-max-order-quantitys" => ["label" => "Verkaufsliste Struktur Minimum/Maximumbestellmengen", "filters" => ["salesListStructureId"]],
        "sales-list-structure-promotion-prices" => ["label" => "Verkaufsliste Stuktur Aktionspreise", "filters" => ["salesListPromotionPeriodId"]],
        "sales-list-structure-texts" => ["label" => "Verkaufsliste Struktur Texte", "filters" => ["salesListStructureId", "languageId"]],
        "sales-list-structures" => ["label" => "Verkaufsliste Struktur", "filters" => ["salesListId"]],
        "sales-list-texts" => ["label" => "Verkaufsliste Texte", "filters" => ["salesListId", "languageId"]],
        "sales-list-tier-prices-obsoletes" => ["label" => "Verkaufsliste Staffelrpeise (obsolet)", "filters" => ["salesListStructureId"]],
        "sales-lists" => ["label" => "Verkaufsliste", "filters" => ["validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "startTimeFrom", "startTimeTo", "endTimeFrom", "endTimeTo", "typeValues", "preOrderPeriodValues", "preOrderTypeLeadDaysValues", "reservationTypeAdvanceDaysValues", "isGrossNetValues"]],
        "sales-statistic-structures" => ["label" => "Verkaufsstatistik Struktur", "filters" => ["salesStatisticId", "typeValues"]],
        "sales-statistics" => ["label" => "Verkaufsstatistik", "filters" => ["dateFrom", "dateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "special-expenses-income-entrys" => ["label" => "Sonderausgaben/-einnahmen", "filters" => ["costCenterId", "dateFrom", "dateTo", "typeValues"]],
        "standard-message-texts" => ["label" => "Nachrichten Standardtext", "filters" => ["userDefId"]],
        "standing-order-structure-order-requisitions" => ["label" => "Dauerauftrag Struktur Bestellanforderung", "filters" => ["standingOrdersStructureId"]],
        "standing-order-structures" => ["label" => "Dauerauftrag Struktur", "filters" => ["standingOrderId"]],
        "standing-orders" => ["label" => "Dauerauftrag", "filters" => ["validFromFrom", "validFromTo", "validToFrom", "validToTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "lastRunFrom", "lastRunTo", "nextRunFrom", "nextRunTo", "leadTimeTypeValues", "typeValues"]],
        "stock-definitions" => ["label" => "Stockdefintion", "filters" => ["stockId"]],
        "stock-locations" => ["label" => "Lagerort", "filters" => ["stockId"]],
        "stock-texts" => ["label" => "Lager Texte", "filters" => ["stockId", "languageId"]],
        "stocks" => ["label" => "Lager", "filters" => ["typeValues"]],
        "sum-sent-purchase-orders" => ["label" => "Statistik versandte Bestellungen", "filters" => ["dateFrom", "dateTo"]],
        "supplier-contact-datas" => ["label" => "Lieferanten Kontaktdaten", "filters" => ["supplierId"]],
        "supplier-delivery-areas" => ["label" => "Lieferantenliefergebiet", "filters" => ["supplierId", "logistikTypeValues"]],
        "supplier-documents" => ["label" => "Lieferant Dokumente", "filters" => ["supplierId", "dateFrom", "dateTo"]],
        "supplier-item-additional-informations" => ["label" => "Lieferantenaritkel Zusatzinformationen (Pistor)", "filters" => ["supplierItemId", "validFrom2From", "validFrom2To", "validTo2From", "validTo2To", "validFrom3From", "validFrom3To", "validTo3From", "validTo3To"]],
        "supplier-item-allergens" => ["label" => "Lieferantenartikel Allergene", "filters" => ["supplierId", "supplierItemId"]],
        "supplier-item-allergens-historys" => ["label" => "Lieferantenartikel  Allergene Historie", "filters" => ["supplierItemId", "validUntilFrom", "validUntilTo"]],
        "supplier-item-assignment-in-recipes" => ["label" => "Artikelzuordnung in Rezept", "filters" => ["supplierItemId", "productStructureId"]],
        "supplier-item-buffers" => ["label" => "Artikelpuffer", "filters" => ["supplierItemId", "validFromFrom", "validFromTo", "validToFrom", "validToTo", "typeValues"]],
        "supplier-item-buffers-status" => ["label" => "Artikelpuffer Status", "filters" => ["supplierItemId", "validFromFrom", "validFromTo", "statusValues"]],
        "supplier-item-commodity-group-texts" => ["label" => "Warengruppe Texte (für künftige Entwicklungen)", "filters" => ["commodityGroupId", "languageId"]],
        "supplier-item-commodity-groups" => ["label" => "Warengruppe (für künftige Entwicklungen)", "filters" => []],
        "supplier-item-control-prices" => ["label" => "Artikelkontrollpreis", "filters" => ["itemPriceId", "validOnDateFrom", "validOnDateTo"]],
        "supplier-item-declaration-substances" => ["label" => "Lieferantenartikel Deklarationspflichtige Stoffe", "filters" => ["supplierId", "supplierItemId"]],
        "supplier-item-declaration-substances-historys" => ["label" => "Lieferantenartikel Deklarationspflichtige Stoffe Historie", "filters" => ["supplierItemId", "validUntilFrom", "validUntilTo"]],
        "supplier-item-entered-lmiv-values" => ["label" => "Lieferantenartikel Eingegebene LMIV-Werte", "filters" => ["supplierItemId"]],
        "supplier-item-historys" => ["label" => "Lieferantenartikelhistorie", "filters" => ["supplierItemId", "validUntilFrom", "validUntilTo"]],
        "supplier-item-nutritional-values" => ["label" => "Lieferantenartikel Nährwerte", "filters" => ["supplierId", "supplierItemId"]],
        "supplier-item-nutritional-values-historys" => ["label" => "Lieferantenartikel Nährwerte Historie", "filters" => ["supplierItemId", "validUntilFrom", "validUntilTo"]],
        "supplier-item-price-cost-center-specifics" => ["label" => "Artikelpreis kostenstellenspezifisch", "filters" => ["validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "supplier-item-price-historys" => ["label" => "Artikelpreishistorie", "filters" => ["supplierId", "supplierItemId", "validFromFrom", "validFromTo", "validToFrom", "validToTo", "typeValues"]],
        "supplier-item-prices" => ["label" => "Artikelpreis", "filters" => ["supplierId", "supplierItemId", "validToFrom", "validToTo", "promotionalPriceValidFromDateFrom", "promotionalPriceValidFromDateTo", "promotionalPriceValidUntilDateFrom", "promotionalPriceValidUntilDateTo", "essenceDateFrom", "essenceDateTo", "statusValidFromFrom", "statusValidFromTo", "fixValidToFrom", "fixValidToTo", "changeDateFrom", "changeDateTo", "creationDateFrom", "creationDateTo", "statusValues", "discountTypeValues"]],
        "supplier-item-supplier-contracts" => ["label" => "Lieferantenvereinbarung", "filters" => ["supplierItemId", "validFromFrom", "validFromTo", "validToFrom", "validToTo"]],
        "supplier-item-texts" => ["label" => "Lieferantenartikel Texte", "filters" => ["supplierItemId", "languageId"]],
        "supplier-items" => ["label" => "Lieferantenartikel", "filters" => ["supplierId", "imageValidFromFrom", "imageValidFromTo", "imageValidToFrom", "imageValidToTo"]],
        "supplier-product-class-discounts" => ["label" => "Lieferanten Produktklassenrabatte", "filters" => ["supplierId", "discountArticleValues"]],
        "supplier-tenant-specific-settings" => ["label" => "Lieferant mandatenspezifische Einstellungen", "filters" => ["supplierId", "formatOrdersValues", "formatDesadvValues", "formatInvoiceValues", "mediumOrdersValues", "mediumDesadvValues", "mediumInvoiceValues", "chargeTypeValues", "discountTypValues", "invoiceVatModeValues", "deliveryNotereportModeValues"]],
        "supplier-units-tenant-specifics" => ["label" => "Lieferanteneinheiten mandatenspezifisch", "filters" => []],
        "suppliers" => ["label" => "Lieferant", "filters" => ["mediumPricatValues", "mediumOrdersValues", "mediumDesadvValues", "mediumInvoiceValues", "formatPricatValues", "formatOrdersValues", "formatDesadvValues", "formatInvoiceValues", "typeValues", "exportLevelValues"]],
        "surcharge-class" => ["label" => "Aufschlagsklassen", "filters" => []],
        "surcharge-class-texts" => ["label" => "Aufschlagsklasse Texte", "filters" => ["surchargeClassId", "languageId"]],
        "surcharges-main-cost-center-specifics" => ["label" => "Aufschläge hauptkostenstellenspezifisch", "filters" => ["surchargeClassId"]],
        "surcharges-tenant-specifics" => ["label" => "Mandantenspezifische Aufschlagsklasse", "filters" => ["surchargeClassId"]],
        "team-members" => ["label" => "Teammitglieder", "filters" => ["teamId"]],
        "teams" => ["label" => "Team", "filters" => []],
        "template-order-structures" => ["label" => "Musterauftrag Struktur", "filters" => ["templateOrderId"]],
        "template-order-texts" => ["label" => "Musterauftrag Texte", "filters" => ["templateOrderId", "languageId"]],
        "template-orders" => ["label" => "Musterauftrag", "filters" => ["validFromFrom", "validFromTo", "validToFrom", "validToTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo"]],
        "tendering-items" => ["label" => "Ausschreibung Artikel", "filters" => ["tenderingsStructureId", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo"]],
        "tendering-items-archivs" => ["label" => "Ausschreibung Artikel Archiv", "filters" => ["dateFrom", "dateTo", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo"]],
        "tendering-structures" => ["label" => "Ausschreibung Struktur", "filters" => ["tenderingId"]],
        "tendering-suppliers" => ["label" => "Ausschreibung Lieferanten", "filters" => ["tenderingId", "statusValidFromFrom", "statusValidFromTo", "statusValues"]],
        "tenderings" => ["label" => "Ausschreibung", "filters" => ["statusValidFromFrom", "statusValidFromTo", "validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "submissionDateFrom", "submissionDateTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "deadlineFrom", "deadlineTo", "statusValues"]],
        "tour-texts" => ["label" => "Tour Texte", "filters" => ["tourId", "languageId"]],
        "tours" => ["label" => "Tour", "filters" => ["startDateFrom", "startDateTo", "endDateFrom", "endDateTo", "dedefleetStatusValues"]],
        "unit-texts" => ["label" => "Einheit Texte", "filters" => ["unitId", "languageId"]],
        "units" => ["label" => "Einheit", "filters" => []],
        "user-defs" => ["label" => "User", "filters" => ["dateBirthFrom", "dateBirthTo", "lastLoginFrom", "lastLoginTo", "passwordTimestampFrom", "passwordTimestampTo", "activeTimestampFrom", "activeTimestampTo", "inactiveTimestampFrom", "inactiveTimestampTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "lastCatstattypeInfoFrom", "lastCatstattypeInfoTo"]],
        "user-group-print-layout-permissions" => ["label" => "Usergruppe Berechtigung für Drucklayouts", "filters" => ["userGroupId"]],
        "user-group-report-template-permissions" => ["label" => "UsergruppeBerechtigung Reporttemplate", "filters" => ["reportTemplateId", "userGroupId"]],
        "user-report-configurations" => ["label" => "User Reportkonfiguration", "filters" => ["reportTemplateId", "userDefId"]],
        "week-production-structures" => ["label" => "Wochenproduktion Struktur", "filters" => ["weekProductionId"]],
        "week-productions" => ["label" => "Wochenproduktion", "filters" => ["validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "statusValues"]],
        "weekly-menu-plan-structures" => ["label" => "Wochenmenüplanstruktur", "filters" => ["weekMenuPlanId"]],
        "weekly-menu-plan-texts" => ["label" => "Wochenmenüplan Texte", "filters" => ["weekMenuPlanId", "languageId"]],
        "weekly-menu-plans" => ["label" => "Wochenmenüplan", "filters" => ["validFromFrom", "validFromTo", "validUntilFrom", "validUntilTo", "creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "calcBestTimestampFrom", "calcBestTimestampTo", "transferTimestampFrom", "transferTimestampTo", "typeValues"]],
        "work-task-documents" => ["label" => "Arbeitsaufgabe Dokumente", "filters" => ["workTaskId", "creationDateFrom", "creationDateTo"]],
        "work-tasks" => ["label" => "Arbeitsaufgabe", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "closeDateFrom", "closeDateTo", "statusValues"]],
        "workflow-node-structure-texts" => ["label" => "Workflowknoten Struktur Texte", "filters" => ["workflowNodeStructureId", "languageId"]],
        "workflow-node-structures" => ["label" => "Workflowknoten Struktur", "filters" => ["workflowId"]],
        "workflow-node-texts" => ["label" => "Workflowknoten Texte", "filters" => ["workflowNodeId", "languageId"]],
        "workflow-nodes" => ["label" => "Workflowknoten", "filters" => ["workflowId", "typeValues", "nodeActionValues"]],
        "workflow-status" => ["label" => "Workflowstatus", "filters" => ["workflowId", "codeFrom", "codeTo"]],
        "workflow-status-texts" => ["label" => "Workflowstatus Texte", "filters" => ["languageId"]],
        "workflow-texts" => ["label" => "Workflow Texte", "filters" => ["workflowId", "languageId"]],
        "workflows" => ["label" => "Workflow", "filters" => ["creationDateFrom", "creationDateTo", "changeDateFrom", "changeDateTo", "workflowTypeValues"]],
    ];

    /** @return array<int, string> Alle gültigen Ressourcen-Slugs. */
    public static function all(): array
    {
        return array_keys(self::REGISTRY);
    }

    public static function exists(string $slug): bool
    {
        return isset(self::REGISTRY[$slug]);
    }

    /** @return array<int, string> Dokumentierte Query-Filter der Ressource (ohne pageNumber/pageSize). */
    public static function filters(string $slug): array
    {
        return self::REGISTRY[$slug]["filters"] ?? [];
    }

    /** Menschenlesbares Label (dt.) der Ressource, z.B. "Behörde" für "agencys". */
    public static function label(string $slug): ?string
    {
        return self::REGISTRY[$slug]["label"] ?? null;
    }
}
