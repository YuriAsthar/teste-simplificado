<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stripe-standard tax ID document types.
 *
 * Every case maps to a Stripe tax_id type code. These codes are used for
 * billing and tax-compliance purposes across all supported countries.
 *
 * @see https://docs.stripe.com/api/tax_ids/object
 */
enum DocumentType: string
{
    /** Argentine tax identification number (CUIT). */
    case ArCuit = 'ar_cuit';

    /** Brazilian individual taxpayer registry (CPF). */
    case BrCpf = 'br_cpf';

    /** Brazilian corporate taxpayer registry (CNPJ). */
    case BrCnpj = 'br_cnpj';

    /** Bolivian tax identification number (TIN). */
    case BoTin = 'bo_tin';

    /** Canadian Business Number (BN). */
    case CaBn = 'ca_bn';

    /** Canadian GST/HST account number. */
    case CaGstHst = 'ca_gst_hst';

    /** Canadian PST (British Columbia) account number. */
    case CaPstBc = 'ca_pst_bc';

    /** Canadian PST (Manitoba) account number. */
    case CaPstMb = 'ca_pst_mb';

    /** Canadian PST (Saskatchewan) account number. */
    case CaPstSk = 'ca_pst_sk';

    /** Canadian QST (Québec) account number. */
    case CaQst = 'ca_qst';

    /** Chilean Tax Identification Number (RUT). */
    case ClTin = 'cl_tin';

    /** Colombian tax identification number (NIT). */
    case CoNit = 'co_nit';

    /** Costa Rican tax identification number (cedula física or jurídica). */
    case CrTin = 'cr_tin';

    /** Dominican Republic national taxpayer registry number (RNC). */
    case DoRcn = 'do_rcn';

    /** Ecuadorian tax identification number (RUC). */
    case EcRuc = 'ec_ruc';

    /** Mexican tax identification number (RFC). */
    case MxRfc = 'mx_rfc';

    /** Paraguayan tax identification number (RUC). */
    case PyRuc = 'py_ruc';

    /** Peruvian tax identification number (RUC). */
    case PeRuc = 'pe_ruc';

    /** United States Employer Identification Number (EIN). */
    case UsEin = 'us_ein';

    /** Venezuelan tax identification number (RIF). */
    case VeRif = 've_rif';

    /** Andorran non-resident tax number (NRT). */
    case AdNrt = 'ad_nrt';

    /** European Union VAT identification number. */
    case EuVat = 'eu_vat';

    /** European Union One-Stop Shop VAT identification number. */
    case EuOssVat = 'eu_oss_vat';

    /** United Kingdom VAT identification number. */
    case GbVat = 'gb_vat';

    /** German Steuernummer (local tax number). */
    case DeStn = 'de_stn';

    /** Swiss business identification number (UID). */
    case ChUid = 'ch_uid';

    /** Swiss VAT identification number. */
    case ChVat = 'ch_vat';

    /** Spanish company tax identification number (CIF). */
    case EsCif = 'es_cif';

    /** Italian company tax identification number (codice fiscale). */
    case ItCf = 'it_cf';

    /** Norwegian VAT identification number. */
    case NoVat = 'no_vat';

    /** Norwegian VAT on Electronic Services (VOEC) number. */
    case NoVoec = 'no_voec';

    /** Icelandic VAT identification number (VSK / VSK-númer). */
    case IsVat = 'is_vat';

    /** Liechtenstein business identification number (UID). */
    case LiUid = 'li_uid';

    /** Liechtenstein VAT identification number. */
    case LiVat = 'li_vat';

    /** Croatian personal identification number (OIB). */
    case HrOib = 'hr_oib';

    /** Hungarian tax identification number (adószám). */
    case HuTin = 'hu_tin';

    /** Israeli VAT identification number. */
    case IlVat = 'il_vat';

    /** Australian Business Number (ABN). */
    case AuAbn = 'au_abn';

    /** Australian Taxation Office ARN (for non-residents). */
    case AuArn = 'au_arn';

    /** Chinese tax identification number. */
    case CnTin = 'cn_tin';

    /** Hong Kong Business Registration number. */
    case HkBr = 'hk_br';

    /** Indian Goods and Services Tax identification number (GSTIN). */
    case InGst = 'in_gst';

    /** Indonesian taxpayer identification number (NPWP). */
    case IdNpwp = 'id_npwp';

    /** Japanese Corporate Number (法人番号). */
    case JpCorporateNumber = 'jp_cn';

    /** Japanese Registered Foreign Business Number (登録外国法人番号). */
    case JpRegisteredForeignBusinessNumber = 'jp_rn';

    /** Japanese Tax Registration Number (税務登録番号). */
    case JpTaxRegistrationNumber = 'jp_trn';

    /** Korean Business Registration Number (사업자등록번호). */
    case KrBrn = 'kr_brn';

    /** Malaysian tax identification number (MyTIN / ITN). */
    case MyItn = 'my_itn';

    /** Malaysian Sales and Service Tax (SST) number. */
    case MySst = 'my_sst';

    /** Malaysian Foreign Registered Person (FRP) number. */
    case MyFrp = 'my_frp';

    /** New Zealand GST registration number. */
    case NzGst = 'nz_gst';

    /** Philippine Tax Identification Number (TIN). */
    case PhTin = 'ph_tin';

    /** Singapore GST registration number. */
    case SgGst = 'sg_gst';

    /** Singapore Unique Entity Number (UEN). */
    case SgUen = 'sg_uen';

    /** Thai VAT registration number. */
    case ThVat = 'th_vat';

    /** Taiwanese VAT / Unified Business Number (統一編號). */
    case TwVat = 'tw_vat';

    /** Vietnamese tax identification number (MST). */
    case VnTin = 'vn_tin';

    /** United Arab Emirates Tax Registration Number (TRN). */
    case AeTrn = 'ae_trn';

    /** Bahraini VAT registration number. */
    case BhVat = 'bh_vat';

    /** Omani VAT registration number. */
    case OmVat = 'om_vat';

    /** Saudi Arabian VAT registration number. */
    case SaVat = 'sa_vat';

    /** South African VAT identification number. */
    case ZaVat = 'za_vat';

    /** Kenyan Personal Identification Number (PIN). */
    case KePin = 'ke_pin';

    /** Nigerian Tax Identification Number (TIN). */
    case NgTin = 'ng_tin';

    /** Egyptian Tax Identification Number (TIN). */
    case EgTin = 'eg_tin';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /**
     * @return list<DocumentType>
     */
    public static function allowedForCountry(string $country): array
    {
        return match (strtoupper($country)) {
            'BRA' => [self::BrCpf, self::BrCnpj],
            'USA', 'US' => [self::UsEin],
            'MEX', 'MX' => [self::MxRfc],
            'ARG', 'AR' => [self::ArCuit],
            'CAN', 'CA' => [self::CaBn, self::CaGstHst, self::CaPstBc, self::CaPstMb, self::CaPstSk, self::CaQst],
            'CHL', 'CL' => [self::ClTin],
            'COL', 'CO' => [self::CoNit],
            'CRI', 'CR' => [self::CrTin],
            'DOM', 'DO' => [self::DoRcn],
            'ECU', 'EC' => [self::EcRuc],
            'PER', 'PE' => [self::PeRuc],
            'PRY', 'PY' => [self::PyRuc],
            'VEN', 'VE' => [self::VeRif],
            'BOL', 'BO' => [self::BoTin],
            'GBR', 'GB' => [self::GbVat, self::EuVat],
            'DEU', 'DE' => [self::DeStn, self::EuVat],
            'ESP', 'ES' => [self::EsCif, self::EuVat],
            'ITA', 'IT' => [self::ItCf, self::EuVat],
            'FRA', 'FR' => [self::EuVat],
            'CHE', 'CH' => [self::ChUid, self::ChVat],
            'HRV', 'HR' => [self::HrOib, self::EuVat],
            'HUN', 'HU' => [self::HuTin, self::EuVat],
            'ISL', 'IS' => [self::IsVat],
            'ISR', 'IL' => [self::IlVat],
            'NOR', 'NO' => [self::NoVat, self::NoVoec],
            'AND', 'AD' => [self::AdNrt],
            'LIE', 'LI' => [self::LiUid, self::LiVat],
            'AUT', 'AT', 'BEL', 'BE', 'BGR', 'BG', 'CYP', 'CY', 'CZE', 'CZ', 'DNK', 'DK', 'EST', 'EE', 'FIN', 'FI', 'GRC', 'GR', 'IRL', 'IE', 'LVA', 'LV', 'LTU', 'LT', 'LUX', 'LU', 'MLT', 'MT', 'NLD', 'NL', 'POL', 'PL', 'PRT', 'PT', 'ROU', 'RO', 'SVK', 'SK', 'SVN', 'SI', 'SWE', 'SE' => [self::EuVat, self::EuOssVat],
            'AUS', 'AU' => [self::AuAbn, self::AuArn],
            'CHN', 'CN' => [self::CnTin],
            'HKG', 'HK' => [self::HkBr],
            'IND', 'IN' => [self::InGst],
            'IDN', 'ID' => [self::IdNpwp],
            'JPN', 'JP' => [self::JpCorporateNumber, self::JpRegisteredForeignBusinessNumber, self::JpTaxRegistrationNumber],
            'KOR', 'KR' => [self::KrBrn],
            'MYS', 'MY' => [self::MyFrp, self::MyItn, self::MySst],
            'NZL', 'NZ' => [self::NzGst],
            'PHL', 'PH' => [self::PhTin],
            'SGP', 'SG' => [self::SgGst, self::SgUen],
            'THA', 'TH' => [self::ThVat],
            'TWN', 'TW' => [self::TwVat],
            'VNM', 'VN' => [self::VnTin],
            'ARE', 'AE' => [self::AeTrn],
            'BHR', 'BH' => [self::BhVat],
            'OMN', 'OM' => [self::OmVat],
            'SAU', 'SA' => [self::SaVat],
            'ZAF', 'ZA' => [self::ZaVat],
            'KEN', 'KE' => [self::KePin],
            'NGA', 'NG' => [self::NgTin],
            'EGY', 'EG' => [self::EgTin],
            default => [],
        };
    }
}
