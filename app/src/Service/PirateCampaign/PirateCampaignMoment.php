<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** États déjà constatés par le jeu ; les textes ne déclenchent jamais une transition. */
enum PirateCampaignMoment
{
    case Signal;
    case ReconnaissanceEnRoute;
    case RenseignementRecu;
    case AttaqueEngagee;
    case Defaite;
    case IssueIndecise;
    case RetourDesSurvivants;
    case Victoire;
}
