<?php

namespace Platform\Integrations\Services;

/**
 * Registry aller Endpunkte der cisbox Invoice API v1.
 *
 * Auto-generiert aus der offiziellen OpenAPI-Spezifikation (Version 1.0),
 * abrufbar unter https://cisbox-global.cisbox.com/resteasy/api-docs/invoice/v1
 * und gerendert unter https://api.cisbox.com/docs/ui/invoice-api-v1.
 *
 * 27 Pfade, 27 Operationen in 11 Themenbereichen — allesamt GET.
 * Die API ist vollstaendig lesend; es gibt keinen schreibenden Endpunkt.
 *
 * Der Schluessel ist stets "<METHODE> <Pfad>", z.B. "GET /invoices".
 * Pfad-Platzhalter stehen in geschweiften Klammern ({barcodeNo}) und werden von
 * {@see CisboxApiService::call()} aus den uebergebenen Argumenten ersetzt.
 *
 * @see \Platform\Integrations\Services\CisboxApiService
 */
final class CisboxResource
{
    /** Themenbereiche der API mit der Anzahl ihrer Operationen. */
    public const TAGS = [
        "accounts" => 2,
        "assignments" => 2,
        "code 1" => 2,
        "code 2" => 2,
        "code 3" => 2,
        "creditors" => 3,
        "deliveries" => 3,
        "invoices" => 5,
        "payment types" => 2,
        "payments" => 2,
        "tax codes" => 2,
    ];

    /**
     * Alle Operationen mit ihren Parametern.
     *
     * tag       Themenbereich; summary Kurzbeschreibung aus der Spezifikation
     * opid      operationId der Spezifikation
     * query     erlaubte Query-Parameter; required davon die pflichtigen (hier durchgehend leer)
     * path      Pfad-Platzhalter
     * enums     erlaubte Werte je Query-Parameter, soweit die Spezifikation sie festlegt
     * paginated Liste mit page/itemsPerPage und meta-Block in der Antwort
     * single    "data" traegt genau einen Datensatz — auch Einzelabrufe liefern ein Array
     * returns   DTO der Eintraege in "data" — Felder siehe self::FIELDS
     */
    public const OPERATIONS = [
        "GET /masterdata/accounts" => [
            "tag" => "accounts", "opid" => "getAccounts",
            "summary" => "Get a list of accounts",
            "query" => ["page", "itemsPerPage", "q", "updatedSince", "type"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => ["type" => ["UNDEFINED", "NONE", "EXPENSES", "INVENTORIES", "ASSETS", "SUBLEDGER_RECIEVABLES", "SUBLEDGER_PAYABLES", "REVENUES", "ACTIVA", "PASSIVA"]],
            "returns" => "AccountListApiDto",
        ],
        "GET /masterdata/accounts/{accountNo}" => [
            "tag" => "accounts", "opid" => "getAccount",
            "summary" => "Get a single account",
            "query" => [], "required" => [],
            "path" => ["accountNo"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "AccountApiDto",
        ],
        "GET /masterdata/assignments" => [
            "tag" => "assignments", "opid" => "getAssignments",
            "summary" => "Get a list of assignments",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "AssignmentApiDto",
        ],
        "GET /masterdata/assignments/{assignmentNo}" => [
            "tag" => "assignments", "opid" => "getAssociation",
            "summary" => "Get a single assignment",
            "query" => [], "required" => [],
            "path" => ["assignmentNo"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "AssignmentApiDto",
        ],
        "GET /masterdata/code1" => [
            "tag" => "code 1", "opid" => "getCode1List",
            "summary" => "returns a List of all Code1 Entries (optionally filtered) as an array",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "Code1ApiDto",
        ],
        "GET /masterdata/code1/{code1No}" => [
            "tag" => "code 1", "opid" => "getCode1",
            "summary" => "Get a single Code 1",
            "query" => [], "required" => [],
            "path" => ["code1No"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "Code1ApiDto",
        ],
        "GET /masterdata/code2" => [
            "tag" => "code 2", "opid" => "getCode2List",
            "summary" => "returns a List of all Code2 Entries (optionally filtered) as an array",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "Code2ApiDto",
        ],
        "GET /masterdata/code2/{code2No}" => [
            "tag" => "code 2", "opid" => "getCode2",
            "summary" => "Get a single Code 2",
            "query" => [], "required" => [],
            "path" => ["code2No"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "Code2ApiDto",
        ],
        "GET /masterdata/code3" => [
            "tag" => "code 3", "opid" => "getCode3List",
            "summary" => "returns a List of all Code3 Entries (optionally filtered) as an array",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "Code3ApiDto",
        ],
        "GET /masterdata/code3/{code3No}" => [
            "tag" => "code 3", "opid" => "getCode3",
            "summary" => "Get a single Code 3",
            "query" => [], "required" => [],
            "path" => ["code3No"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "Code3ApiDto",
        ],
        "GET /creditors" => [
            "tag" => "creditors", "opid" => "getCreditorList",
            "summary" => "Get a list of creditors",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "CreditorBaseApiDto",
        ],
        "GET /creditors/{creditorNo}" => [
            "tag" => "creditors", "opid" => "getCreditor",
            "summary" => "Get a single creditor",
            "query" => [], "required" => [],
            "path" => ["creditorNo"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "CreditorBaseApiDto",
        ],
        "GET /creditors/{creditorNo}/items" => [
            "tag" => "creditors", "opid" => "getItemsForCreditor",
            "summary" => "Get a list of items for a creditor",
            "query" => ["page", "itemsPerPage", "q"], "required" => [],
            "path" => ["creditorNo"], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "ItemApiDto",
        ],
        "GET /deliveries" => [
            "tag" => "deliveries", "opid" => "getDeliveries",
            "summary" => "Get a list of deliveries",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "DeliveryApiDto",
        ],
        "GET /deliveries/{deliveryId}" => [
            "tag" => "deliveries", "opid" => "getDelivery",
            "summary" => "Get a single delivery",
            "query" => [], "required" => [],
            "path" => ["deliveryId"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "DeliveryApiDto",
        ],
        "GET /deliveries/{deliveryId}/positions" => [
            "tag" => "deliveries", "opid" => "getDeliveryPositions",
            "summary" => "Get the positions for a delivery",
            "query" => [], "required" => [],
            "path" => ["deliveryId"], "paginated" => false, "single" => false,
            "enums" => [],
            "returns" => "DeliveryPositionApiDto",
        ],
        "GET /invoices" => [
            "tag" => "invoices", "opid" => "getInvoices",
            "summary" => "Get a list of invoices",
            "query" => ["page", "itemsPerPage", "q", "updatedSince", "invoiceDateFrom", "invoiceDateTo", "accountingPeriodFrom", "accountingPeriodTo", "creditorNo", "code1", "cancelled"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "InvoiceApiDto",
        ],
        "GET /invoices/pending" => [
            "tag" => "invoices", "opid" => "getPendingInvoices",
            "summary" => "Get a list of pending invoices (open items)",
            "query" => ["page", "itemsPerPage", "q", "updatedSince", "invoiceDateFrom", "invoiceDateTo", "accountingPeriodFrom", "accountingPeriodTo", "creditorNo", "code1", "status"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => ["status" => ["NOT_APPROVED", "APPROVED", "IN_PROCESS", "CANCELLED", "PAID"]],
            "returns" => "PendingInvoiceApiDto",
        ],
        "GET /invoices/{barcodeNo}" => [
            "tag" => "invoices", "opid" => "getInvoice",
            "summary" => "Get a single invoice",
            "query" => [], "required" => [],
            "path" => ["barcodeNo"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "InvoiceApiDto",
        ],
        "GET /invoices/{barcodeNo}/items" => [
            "tag" => "invoices", "opid" => "getItemsForInvoice",
            "summary" => "Get the items for an invoice",
            "query" => [], "required" => [],
            "path" => ["barcodeNo"], "paginated" => false, "single" => false,
            "enums" => [],
            "returns" => "InvoiceApiInvoiceItemDto",
        ],
        "GET /invoices/{barcodeNo}/pdf" => [
            "tag" => "invoices", "opid" => "getPdfForInvoice",
            "summary" => "Get the PDF file for an invoice",
            "query" => [], "required" => [],
            "path" => ["barcodeNo"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "FileApiDto",
        ],
        "GET /masterdata/paymenttypes" => [
            "tag" => "payment types", "opid" => "getPaymentTypes",
            "summary" => "Get a list of payment types, including default and user-defined ones",
            "query" => ["page", "itemsPerPage", "q"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "PaymentMethodApiDto",
        ],
        "GET /masterdata/paymenttypes/{paymentTypeId}" => [
            "tag" => "payment types", "opid" => "getPaymentType",
            "summary" => "Get a single payment type. Falls back to the default payment type with the given ordinal if no user-defined payment type matches.",
            "query" => [], "required" => [],
            "path" => ["paymentTypeId"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "PaymentMethodApiDto",
        ],
        "GET /payments" => [
            "tag" => "payments", "opid" => "getPayments",
            "summary" => "Get a list of payments",
            "query" => ["page", "itemsPerPage", "q", "createdFrom", "createdTo", "status"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => ["status" => ["UNDEFINED", "PROPOSAL", "WORKING", "STATUS_3", "STATUS_4", "ERROR", "WAITING_FOR_FINAPI", "WAITING_FOR_CREDITOR_PAYMENT", "COMPLETED"]],
            "returns" => "PaymentApiDto",
        ],
        "GET /payments/{paymentId}" => [
            "tag" => "payments", "opid" => "getPayment",
            "summary" => "Get a single payment",
            "query" => [], "required" => [],
            "path" => ["paymentId"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "PaymentApiDto",
        ],
        "GET /masterdata/taxcodes" => [
            "tag" => "tax codes", "opid" => "getTaxCodes",
            "summary" => "Get a list of tax codes",
            "query" => ["page", "itemsPerPage", "q", "updatedSince"], "required" => [],
            "path" => [], "paginated" => true, "single" => false,
            "enums" => [],
            "returns" => "TaxCodeApiDto",
        ],
        "GET /masterdata/taxcodes/{taxCodeId}" => [
            "tag" => "tax codes", "opid" => "getTaxCodes_1",
            "summary" => "Get a single tax code",
            "query" => [], "required" => [],
            "path" => ["taxCodeId"], "paginated" => false, "single" => true,
            "enums" => [],
            "returns" => "TaxCodeApiDto",
        ],
    ];

    /**
     * Felder der Antwort-DTOs: Name => "Typ — Beschreibung".
     *
     * Dient der Feld-Projektion ("fields") und dem Uebersichts-Tool. Die
     * Beschreibungen stammen woertlich aus der Spezifikation und sind
     * deshalb englisch.
     */
    public const FIELDS = [
        "AccountApiDto" => [
            "accountNo" => "string — The account number identifying the general ledger account",
            "name" => "string — Descriptive name of the account",
            "type" => "string UNDEFINED|NONE|EXPENSES|INVENTORIES|ASSETS|SUBLEDGER_RECIEVABLES|SUBLEDGER_PAYABLES|REVENUES|ACTIVA|PASSIVA — Type of the account",
            "accountingGroup" => "integer — Financial accounting group the account belongs to",
            "bookingMode" => "string UNDEFINED|BOOKING_NOT_PERMITTED|BOOKING_WITHOUT_CODE1_ONLY|BOOKING_WITH_CODE1_OPTIONAL|BOOKING_WITH_CODE1_ONLY — Whether and how postings to this account are permitted",
            "taxRate" => "number — Default VAT/tax rate applied to postings on this account, in percent",
            "automatic" => "boolean — Whether this is an automatic account with an implicit tax posting",
            "deactivated" => "boolean — Whether the account is deactivated and no longer available for new postings",
            "permittedCode1" => "array<Code1ApiDto> — Code1 permitted for cost assignment on this account; empty means no restriction",
            "bookingModeCode2" => "string UNDEFINED|NOT_PERMITTED|OPTIONAL|REQUIRED — Whether and how a Code2 value must be provided when posting to this account",
            "permittedCode2" => "array<Code2ApiDto> — Code2 permitted for cost assignment on this account; empty means no restriction",
            "bookingModeCode3" => "string UNDEFINED|NOT_PERMITTED|OPTIONAL|REQUIRED — Whether and how a Code3 value must be provided when posting to this account",
            "permittedCode3" => "array<Code3ApiDto> — Code3 values permitted for cost assignment on this account; empty means no restriction",
            "permittedTaxCodes" => "array<TaxCodeApiDto> — Tax/VAT codes permitted for postings on this account; empty means no restriction",
        ],
        "AccountListApiDto" => [
            "accountNo" => "string — The account number identifying the general ledger account",
            "name" => "string — Descriptive name of the account",
            "type" => "string UNDEFINED|NONE|EXPENSES|INVENTORIES|ASSETS|SUBLEDGER_RECIEVABLES|SUBLEDGER_PAYABLES|REVENUES|ACTIVA|PASSIVA — Type of the account",
            "accountingGroup" => "integer — Financial accounting group the account belongs to",
            "bookingMode" => "string UNDEFINED|BOOKING_NOT_PERMITTED|BOOKING_WITHOUT_CODE1_ONLY|BOOKING_WITH_CODE1_OPTIONAL|BOOKING_WITH_CODE1_ONLY — Whether and how postings to this account are permitted",
            "taxRate" => "number — Default VAT/tax rate applied to postings on this account, in percent",
            "automatic" => "boolean — Whether this is an automatic account with an implicit tax posting",
            "deactivated" => "boolean — Whether the account is deactivated and no longer available for new postings",
            "bookingModeCode2" => "string UNDEFINED|NOT_PERMITTED|OPTIONAL|REQUIRED — Whether and how a Code2 value must be provided when posting to this account",
            "bookingModeCode3" => "string UNDEFINED|NOT_PERMITTED|OPTIONAL|REQUIRED — Whether and how a Code3 value must be provided when posting to this account",
        ],
        "AssignmentApiDto" => [
            "assignmentNo" => "string — The assignment number identifying the assignment",
            "name" => "string — Descriptive name of the assignment",
            "confidential" => "boolean — Whether the assignment is confidential",
            "deactivated" => "boolean — Whether the assignment is deactivated",
        ],
        "Code1ApiDto" => [
            "code1No" => "string — The Code1 number",
            "name" => "string — Descriptive name of the Code1",
            "deactivated" => "boolean — Whether this Code1 is deactivated",
        ],
        "Code2ApiDto" => [
            "code2No" => "string — The Code2 number",
            "name" => "string — Descriptive name of the Code2",
            "deactivated" => "boolean — Whether this Code2 is deactivated",
        ],
        "Code3ApiDto" => [
            "code3No" => "string — The Code3 number",
            "name" => "string — Descriptive name of the Code3",
            "deactivated" => "boolean — Whether this Code3 is deactivated",
        ],
        "CreditorBaseApiDto" => [
            "creditorNo" => "integer — Unique creditor (vendor) number",
            "matchCode" => "string — Short match code / abbreviation for the creditor",
            "name1" => "string — First line of the creditor's name",
            "name2" => "string — Second line of the creditor's name",
            "name3" => "string — Third line of the creditor's name",
            "street" => "string — Street and house number of the creditor's address",
            "postcode" => "string — Postal code of the creditor's address",
            "city" => "string — City of the creditor's address",
            "country" => "string — Country code of the creditor's address",
            "currency" => "string — Currency used for the creditor",
            "gln" => "string — Global Location Number (GLN/ILN) of the creditor",
            "vatId" => "string — VAT identification number",
            "taxId" => "string — Tax number of the creditor",
            "blockStatus" => "boolean — Indicates whether the creditor is blocked",
            "blockPayment" => "boolean — Indicates whether payment to the creditor is blocked",
            "bankName" => "string — Name of the creditor's bank",
            "bankCountry" => "string — Country code of the creditor's bank",
            "bankStreet" => "string — Street of the creditor's bank address",
            "bankPostcode" => "string — Postal code of the creditor's bank address",
            "bankCity" => "string — City of the creditor's bank address",
            "accountHolder" => "string — Name of the bank account holder",
            "bic" => "string — Bank Identifier Code (BIC/SWIFT) of the creditor's bank",
            "iban" => "string — International Bank Account Number (IBAN) of the creditor",
            "paymentMethod" => "integer — Payment method ID of the creditor",
            "subledgerAccountNo" => "string — Subledger / accounts payable account number",
            "notificationEmail" => "string — Email address used for payment notification",
            "discountDays" => "integer — Number of days within which the cash discount applies",
            "discountPercent" => "number — Cash discount percentage",
            "netDays" => "integer — Number of days for net payment",
            "phoneNo" => "string — Phone number of the creditor",
            "url" => "string — Website / internet address of the creditor",
            "email" => "string — General email address of the creditor",
            "invoiceNote" => "string — Note shown on the invoice for this creditor",
            "creditorNote" => "string — General note about the creditor",
            "orderNote" => "string — Note used for orders placed with this creditor",
            "contact1" => "CreditorContactApiDto",
            "contact2" => "CreditorContactApiDto",
            "contact3" => "CreditorContactApiDto",
            "contact4" => "CreditorContactApiDto",
            "erpId" => "string — External ERP system identifier of the creditor, depending on the active integration module",
            "workflow" => "string UNKNOWN|DEFAULT|APPROVAL1_ONLY|APPROVAL2_ONLY|APPROVAL1_AND_APPROVAL2|NO_APPROVAL|APPROVAL1_OR_APPROVAL2|APPROVAL1_BEFORE_APPROVAL2 — Workflow mode configured for the creditor's invoices",
            "categoryId" => "integer — Invoice category id assigned to the creditor",
            "exportExpenses" => "string UNKNOWN|ENABLED|DISABLED|LOCKED — Export exception handling configured for the creditor",
            "paymentPriority" => "string UNKNOWN|PAY|VIP|HIGH|MEDIUM|LOW|DO_NOT_PAY — Default payment priority for the creditor",
            "assignmentNo" => "string — Assignment number of the creditor",
            "creationDate" => "string — Date when the creditor record was created",
        ],
        "CreditorContactApiDto" => [
            "name" => "string — Full name of the creditor contact person",
            "phoneNo" => "string — Phone number of the contact person",
            "email" => "string — Email address of the contact person",
        ],
        "DeliveryApiDto" => [
            "id" => "integer — Unique identifier of the delivery",
            "debtorNo" => "integer — Debtor number the delivery belongs to",
            "creditorNo" => "integer — Creditor number of the supplier",
            "deliveryDate" => "string — Delivery date",
            "transferDate" => "string — Date the delivery was transferred/recorded",
            "deliveryNoteNo" => "string — Delivery note number",
            "orderNo" => "string — Order number referenced by the delivery",
            "erpId" => "string — Identifier of the delivery in the external ERP system",
            "externalId" => "string — External identifier of the delivery",
            "currency" => "string — Currency code of the delivery amounts",
            "amountNet" => "number — Net amount of the delivery",
            "amountGross" => "number — Gross amount of the delivery",
            "deviation" => "number — Deviation between expected and actual amounts",
            "invoiceBarcodeNo" => "string — Barcode number of the matched invoice",
            "invoiceNo" => "string — Invoice document number of the matched invoice",
            "approvalStatus" => "integer — Approval status of the delivery",
            "inactive" => "boolean — Whether the delivery is inactive",
        ],
        "DeliveryPositionApiDto" => [
            "id" => "integer — Unique identifier of the delivery position",
            "position" => "integer — Line/position number within the delivery",
            "itemNo" => "string — Item number of the delivered item",
            "itemDescription" => "string — Description of the delivered item",
            "quantity" => "number — Delivered quantity",
            "unit" => "string — Unit of measure for the quantity",
            "unitPrice" => "number — Net unit price of the item",
            "totalPrice" => "number — Total net price of the position",
            "vatRate" => "number — VAT rate applied to the position, in percent",
            "account" => "string — Account the position is booked to",
            "code1" => "string — First cost-assignment code of the position",
            "code2" => "string — Second cost-assignment code of the position",
            "originOrder" => "string — Originating order reference for the position",
        ],
        "FileApiDto" => [
            "fileData" => "array<string> — Raw binary content of the invoice document file encoded as base64",
        ],
        "InvoiceApiDto" => [
            "barcodeNo" => "integer — Barcode number uniquely identifying the invoice document",
            "invoiceNo" => "string — Invoice number as issued by the creditor",
            "invoiceDate" => "string — Invoice date",
            "debtorNo" => "integer — Debtor number this invoice belongs to",
            "creditorNo" => "integer — Creditor number this invoice belongs to",
            "assignment" => "string — Assignment of the invoice",
            "amountGross" => "number — Gross amount of the invoice",
            "currency" => "string — ISO currency code of the invoice",
            "dueDate" => "string — Net due date of the invoice",
            "discountPercent" => "number — Cash discount percentage",
            "discountDate" => "string — Date until which the cash discount can be applied",
            "approval1Status" => "string NOT_APPROVED|APPROVED|APPROVAL_BLOCKED — First approval status",
            "approval2Status" => "string NOT_APPROVED|APPROVED|APPROVAL_BLOCKED — Second approval status",
            "accountingStatus" => "string NOT_ACCOUNTED|PARTIALLY_ACCOUNTED|ACCOUNTED — Accounting / cost assignment status",
            "paymentStatus" => "string NOT_APPROVED|APPROVED|IN_PROCESS|CANCELLED|PAID — Payment / release status of the invoice",
            "deliveryDate" => "string — Service / delivery date",
            "deliverNote" => "string — Delivery note number",
            "bookingText" => "string — Booking text of the invoice",
            "accountingPeriod" => "string — Booking period",
            "category" => "integer — Category code of the invoice",
            "reference" => "string — Reference number of the invoice",
            "orderNo" => "string — Order number of the invoice",
            "paymentText" => "string — Payment / bank transfer text",
            "exportCosts" => "string ACTIVE|INACTIVE|BLOCKED — Export block from the invoice header (Exportsperre)",
        ],
        "InvoiceApiInvoiceItemDto" => [
            "itemNo" => "string — Item number of the invoice item",
            "name" => "string — Description / name of the invoice item",
            "amount" => "number — Ordered / invoiced quantity of the item",
            "unit" => "string — Unit of measure used for billing",
            "unitPrice" => "number — Net price per unit",
            "totalPrice" => "number — Total net amount for the item",
            "lastPrice" => "number — Last purchase price recorded for this item",
            "lastPriceDate" => "string — Date on which the last purchase price was recorded",
            "accounting" => "array<InvoiceApiItemAccountingDto> — Accounting splits for this invoice item",
        ],
        "InvoiceApiItemAccountingDto" => [
            "bookingText" => "string — Booking text of this accounting split",
            "amount" => "number — Quantity assigned to this accounting split",
            "price" => "number — Net amount assigned to this accounting split",
            "taxCode" => "string — Tax code applied to this accounting split",
            "account" => "string — General ledger account number",
            "code1" => "string — First accounting coding dimension",
            "code2" => "string — Second accounting coding dimension",
            "code3" => "string — Third accounting coding dimension",
        ],
        "ItemApiDto" => [
            "itemNo" => "string — Item number identifying the item",
            "name" => "string — Descriptive name of the item",
            "unit" => "string — Unit of measure for the item",
            "vatRate" => "number — VAT rate applied to the item, in percent",
            "taxCode" => "string — Tax code of the item",
            "code1" => "string — First accounting code",
            "code2" => "string — Second accounting code",
            "code3" => "string — Third accounting code",
            "bookingText" => "string — Default booking text for the item",
            "account" => "string — General ledger account assigned to the item",
            "creationTime" => "string — Timestamp when the item was created",
            "lastPrice" => "number — Most recent purchase price of the item",
            "lowestPrice" => "number — Lowest recorded purchase price of the item",
            "highestPrice" => "number — Highest recorded purchase price of the item",
            "averagePrice" => "number — Average purchase price of the item",
        ],
        "PaymentApiDto" => [
            "id" => "integer — Unique identifier of the payment",
            "status" => "string UNDEFINED|PROPOSAL|WORKING|STATUS_3|STATUS_4|ERROR|WAITING_FOR_FINAPI|WAITING_FOR_CREDITOR_PAYMENT|COMPLETED — Current status of the payment",
            "comment" => "string — Optional comment on the payment",
            "amountGross" => "number — Gross payment amount",
            "amountDiscount" => "number — Cash discount amount deducted from the payment",
            "amountForeignCurrency" => "number — Payment amount in the foreign currency, if applicable",
            "creationDate" => "string — Date the payment was created",
            "dueDate" => "string — Due date of the payment",
            "paidDate" => "string — Date the payment was paid",
            "bankAccountId" => "integer — Identifier of the bank account used for the payment, if set",
            "paymentTransactionId" => "integer — Identifier of the associated payment transaction",
            "centralPayment" => "boolean — Whether this is a central payment",
            "online" => "boolean — Whether the payment is processed online",
            "batchBooking" => "boolean — Whether the payment is part of a batch booking",
        ],
        "PaymentMethodApiDto" => [
            "id" => "integer — Identifier of the payment method",
            "name" => "string — Display name of the payment method",
            "deactivated" => "boolean — Indicates whether the payment method is deactivated",
        ],
        "PendingInvoiceApiDto" => [
            "barcodeNo" => "integer — Barcode number uniquely identifying the invoice document",
            "invoiceNo" => "string — Invoice number as issued by the creditor",
            "invoiceDate" => "string — Invoice date",
            "debtorNo" => "integer — Debtor number this invoice belongs to",
            "creditorNo" => "integer — Creditor number this invoice belongs to",
            "currency" => "string — ISO currency code of the open item",
            "originalAmount" => "number — Original gross amount in the original document currency",
            "originalCurrency" => "string — Original ISO currency code of the document",
            "openAmount" => "number — Currently open (unpaid) amount",
            "paidAmount" => "number — Amount already paid against this open item",
            "nextPaymentAmount" => "number — Amount of the next scheduled payment",
            "discountAmount" => "number — Cash discount amount",
            "discountPercent" => "number — Cash discount percentage",
            "dueDate" => "string — Net due date of the open item",
            "discountDate" => "string — Date until which the cash discount can be applied",
            "directDebitDate" => "string — Scheduled direct debit / bank collection date",
            "paymentStatus" => "string NOT_APPROVED|APPROVED|IN_PROCESS|CANCELLED|PAID — Payment / release status of the open item",
            "paymentBlock" => "integer — Payment block indicator; non-zero means payment is blocked.",
            "priority" => "integer — Payment priority of the open item",
            "paymentMethod" => "integer — Payment method ID",
            "documentIban" => "string — IBAN taken from the document",
            "documentBic" => "string — BIC taken from the document",
        ],
        "TaxCodeApiDto" => [
            "taxCode" => "string — The tax/VAT code key identifying the tax code",
            "name" => "string — Descriptive name of the tax code",
            "taxRate" => "number — The tax/VAT rate in percent",
            "defaultTax" => "boolean — Whether this is the default tax code",
            "deactivated" => "boolean — Whether the tax code is deactivated",
        ],
    ];

    /** Existiert diese Operation? Schluessel ist "<METHODE> <Pfad>". */
    public static function exists(string $method, string $path): bool
    {
        return isset(self::OPERATIONS[self::key($method, $path)]);
    }

    /** @return array<string, mixed>|null Metadaten einer Operation. */
    public static function operation(string $method, string $path): ?array
    {
        return self::OPERATIONS[self::key($method, $path)] ?? null;
    }

    /** @return array<int, string> Alle Schluessel eines Themenbereichs. */
    public static function byTag(string $tag): array
    {
        $tag = mb_strtolower(trim($tag));

        return array_keys(array_filter(
            self::OPERATIONS,
            static fn (array $op) => mb_strtolower($op["tag"]) === $tag
        ));
    }

    /**
     * Felder eines DTOs — oder die des Rueckgabetyps einer Operation.
     *
     * @return array<string, string>
     */
    public static function fields(string $dtoOrOperationKey): array
    {
        if (isset(self::FIELDS[$dtoOrOperationKey])) {
            return self::FIELDS[$dtoOrOperationKey];
        }

        $op = self::OPERATIONS[$dtoOrOperationKey] ?? null;

        return $op ? (self::FIELDS[$op["returns"]] ?? []) : [];
    }

    /**
     * Sucht Operationen nach Freitext in Pfad, Beschreibung und operationId.
     *
     * @return array<int, string>
     */
    public static function search(string $needle, int $limit = 40): array
    {
        $needle = mb_strtolower(trim($needle));
        if ($needle === "") {
            return [];
        }

        $hits = [];
        foreach (self::OPERATIONS as $key => $op) {
            $haystack = mb_strtolower($key . " " . $op["summary"] . " " . $op["opid"]);
            if (str_contains($haystack, $needle)) {
                $hits[] = $key;
                if (count($hits) >= $limit) {
                    break;
                }
            }
        }

        return $hits;
    }

    /** Normalisiert "<METHODE> <Pfad>" (Methode gross, genau ein Leerzeichen, fuehrender Slash). */
    public static function key(string $method, string $path): string
    {
        return mb_strtoupper(trim($method)) . " /" . ltrim(trim($path), "/");
    }
}
