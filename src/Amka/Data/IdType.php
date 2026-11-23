<?php

namespace Sonole\GovGr\Amka\Data;

/**
 * The AMKA service's adtType: the kind of identity document `adt` is the number of.
 * Codes and descriptions are the service's own (amkaInfo v1.9); some codes differ from another only by
 * a Greek / Latin lookalike letter (Ν / N, Ι / I, Β / B), so keep them as written.
 */
enum IdType: string
{
    /** ΑΔΕΙΑ ΔΙΑΜΟΝΗΣ ΔΙΚΑΙ.ΔΙΕΘ.ΠΡΟΣ */
    case ResidencePermitInternationalProtection = '1';

    /** ΑΔΕΙΑ ΠΡΟΣΩΡΙΝΗΣ ΠΡΟΣΤΑΣΙΑΣ */
    case TemporaryProtectionPermit = 'Π';

    /** ΑΛΛΟ ΠΑΡΑΣΤΑΤΙΚΟ */
    case OtherDocument = 'Α';

    /** ΑΝΑΒΟΛΗ ΑΠΟΜΑΚΡΥΝΣΗΣ - ΑΔΕΙΑ ΕΡΓΑΣΙΑΣ */
    case PostponementOfRemovalWorkPermit = 'Ν';

    /** ΑΞΙΩΜΑΤΟΥΧΟΙ ΕΥΡΩΠΑΙΚΩΝ ΚΑΙ ΔΙΕΘΝΩΝ ΟΡΓΑΝΙΣΜΩΝ */
    case EuropeanInternationalOfficials = 'Χ';

    /** ΑΥΤΟΤΕΛΕΣ ΕΓΓΡΑΦΟ */
    case SelfCertifiedDocument = '9';

    /** ΒΕΒΑΙΩΣΗ ΚΑΤΑΘΕΣΗΣ ΕΔΤΟ */
    case EdtoDepositCertificate = 'Κ';

    /** ΒΙΝΙΕΤΑ */
    case Vignette = '4';

    /** ΔΕΛΤ.ΑΙΤΗΣΑΝΤΟΣ ΑΣΥΛΟΥ */
    case AsylumApplicantCard = '3';

    /** ΔΕΛΤ.ΑΙΤΟΥΝΤΟΣ ΔΙΕΘ.ΠΡΟΣΤΑΣΙΑΣ */
    case InternationalProtectionApplicantCard = '2';

    /** ΔΕΛΤΙΟ ΔΙΑΜΟΝΗΣ */
    case ResidenceCard = '6';

    /** ΔΕΛΤΙΟ ΜΟΝΙΜΗΣ ΔΙΑΜΟΝΗΣ */
    case PermanentResidenceCard = '7';

    /** ΔΙΑΒΑΤΗΡΙΟ */
    case Passport = 'Δ';

    /** ΔΙΠΛΩΜΑΤΙΚΗ ΤΑΥΤΟΤΗΤΑ */
    case DiplomaticId = 'I';

    /** ΕΙΔΙΚΗ ΒΕΒ/ΣΗ ΝΟΜΙΜΗΣ ΔΙΑΜΟΝΗΣ */
    case SpecialLegalResidenceCertificate = '8';

    /** ΕΙΔΙΚΟ ΔΕΛΤΙΟ ΤΑΥΤΟΤΗΤΑΣ ΟΜΟΓΕΝΟΥΣ */
    case EthnicGreekSpecialIdCard = 'Θ';

    /** ΕΙΔΙΚΟ ΚΑΘΕΣΤΩΣ ΔΙΑΜΟΝΗΣ ΓΙΑ Η.Β. ΚΑΙ Β. ΙΡΛΑΝΔΙΑ */
    case SpecialResidenceStatusUkIreland = 'Ε';

    /** ΕΛΛΗΝΙΚΗ ΑΣΤΥΝΟΜΙΚΗ */
    case GreekPoliceIdCard = 'Τ';

    /** ΕΛΛΗΝΙΚΗ ΣΤΡΑΤΙΩΤΙΚΗ */
    case GreekMilitaryIdCard = 'Σ';

    /** ΕΝΤΟΛΗ ΣΥΝΤΑΞΙΟΔΟΤΗΣΗΣ */
    case PensionOrder = 'Ψ';

    /** ΗΛ. ΑΙΤΗΣΗ (ΑΡΧ.ΧΟΡ.-ΕΠ.-ΑΝ.) */
    case ElectronicApplication = 'Η';

    /** ΘΕΩΡΗΜΕΝΗ ΒΕΒΑΙΩΣΗ ΠΛΟΙΑΡΧΟΥ/ΚΥΒΕΡΝΗΤΗ */
    case CertifiedShipmasterCertificate = 'B';

    /** ΘΕΩΡΗΜΕΝΟ ΝΑΥΤΟΛΟΓΙΟ */
    case CertifiedCrewList = 'N';

    /** ΘΕΩΡΗΣΗ ΤΥΠΟΥ C */
    case TypeCCertification = 'Ρ';

    /** ΘΕΩΡΗΣΗ ΤΥΠΟΥ D */
    case TypeDCertification = 'Ζ';

    /** ΙΔΡΥΣΗ / ΣΥΜΜΕΤΟΧΗ ΣΕ ΕΤΑΙΡΕΙΑ */
    case CompanyFoundationParticipation = 'Ω';

    /** ΚΡΑΤΟΥΜΕΝΟΙ ΣΕ ΣΩΦΡ.ΙΔΡ. */
    case PrisonersInCorrectionalInstitutions = 'Φ';

    /** ΛΗΞΙΑΡΧΙΚΗ ΠΡΑΞΗ ΓΕΝΝΗΣΗΣ */
    case LateBirthRegistration = 'Λ';

    /** ΜΕΡΙΔΑ ΟΙΚΟΓΕΝΕΙΑΚΗ */
    case FamilyRegister = 'Μ';

    /** ΜΠΛΕ ΒΕΒΑΙΩΣΗ */
    case BlueCertificate = '5';

    /** ΤΑΥΤΟΤΗΤΑ ΟΜΟΓΕΝΟΥΣ */
    case EthnicGreekIdCard = 'Ο';

    public function isPoliceIdCard(): bool
    {
        return $this === self::GreekPoliceIdCard;
    }
}
