<?php
declare(strict_types=1);
namespace App\Service\Admin;

use App\Entity\{EditorialImage, EditorialRevision, Player, Universe};
use App\Manager\GameManager;
use App\Service\Player\FirstStepsView;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class EditorialLibrary
{
    public const KINDS = ['title'=>'Titre de commandant','banner'=>'Bannière de profil','page'=>'Bandeau de page','guide'=>'Étape du guide'];
    public const PAGES = ['station'=>'Bourse de ressources','exchange'=>'Marché des joueurs'];
    public const ART = ['art/bourse-station.webp'=>'Quais de la station','art/marche-joueurs.webp'=>'Marché des commandants','art/genesis-avent-panorama-1536.webp'=>'Nuit polaire'];
    public const COLORS = ['gold'=>'Or','cyan'=>'Cyan','silver'=>'Argent'];

    public function __construct(private GameManager $manager, private EditorialActions $actions, private ClockInterface $clock) {}

    public function draft(Player $actor, string $kind, ?Universe $universe, array $fields, ?UploadedFile $upload, ?EditorialRevision $source): EditorialRevision
    {
        if (!isset(self::KINDS[$kind]) || (in_array($kind, ['page','guide'], true) !== ($universe !== null))) {
            throw new \DomainException('Choisissez la portée correspondant au contenu.');
        }
        foreach (['target','step','label','description','color','art'] as $key) {
            if (isset($fields[$key]) && !is_string($fields[$key])) throw new \DomainException('Valeur de formulaire invalide.');
        }
        $code = match ($kind) {
            'page' => $fields['target'] ?? '',
            'guide' => $fields['step'] ?? '',
            default => $source?->code ?? $kind.'_'.bin2hex(random_bytes(8)),
        };
        if (($kind === 'page' && !isset(self::PAGES[$code])) || ($kind === 'guide' && !isset(FirstStepsView::HINTS[$code]))) {
            throw new \DomainException('Destination inconnue.');
        }
        if ($source && ($source->universe?->id !== $universe?->id || $source->kind !== $kind || $source->code !== $code)) {
            throw new \DomainException('Cette version appartient à un autre contenu.');
        }
        $label = trim((string)($fields['label'] ?? ''));
        $description = trim((string)($fields['description'] ?? ''));
        if ($label === '' || mb_strlen($label)>100 || mb_strlen($description)>1500) throw new \DomainException('Indiquez un nom de 1 à 100 caractères et un texte de 1 500 caractères maximum.');
        $content = ['label'=>$label,'description'=>$description];
        if ($kind === 'guide' && $description === '') throw new \DomainException('Renseignez le texte de l’étape.');
        if ($kind === 'title') {
            $content['color'] = $fields['color'] ?? '';
            if (!isset(self::COLORS[$content['color']])) throw new \DomainException('Couleur inconnue.');
        }
        $image = null;
        $reusedImage = null;
        if (in_array($kind, ['banner','page'], true)) {
            $content['art'] = $fields['art'] ?? '';
            if ($upload) $image = $this->image($upload);
            elseif ($content['art'] === 'previous' && isset($source->content['art'])) {
                $content['art'] = $source->content['art'];
                $reusedImage = $source->image;
            }
            elseif (!isset(self::ART[$content['art']])) throw new \DomainException('Choisissez ou importez une image.');
            foreach (['desktop','mobile'] as $device) {
                $position = filter_var($fields[$device] ?? 50, FILTER_VALIDATE_INT);
                if ($position === false || $position < 0 || $position > 100) throw new \DomainException('Le cadrage doit être compris entre 0 et 100 %.');
                $content[$device] = $position;
            }
        }
        return $this->manager->transaction(function () use ($actor,$kind,$universe,$code,$content,$image,$reusedImage): EditorialRevision {
            $this->actions->authorizeLocked($actor);
            if ($image) {
                $this->manager->save($image);
                $this->manager->flush();
                $content['art'] = 'illustrations/edition/'.$image->id.'.webp';
            }
            $revision = new EditorialRevision();
            $revision->kind = $kind;
            $revision->code = $code;
            $revision->universe = $universe;
            $revision->author = $actor;
            $revision->content = $content;
            $revision->image = $image ?? $reusedImage;
            $revision->createdAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $this->manager->save($revision);
            $this->manager->flush();
            $this->actions->log($actor,'editorial_draft','editorial_revision',$revision->id,['code'=>$code]);
            return $revision;
        });
    }

    public function publish(Player $actor, EditorialRevision $revision, string $when): void
    {
        $this->manager->transaction(function () use ($actor,$revision,$when): void {
            $this->actions->authorizeLocked($actor);
            $this->manager->lockCurrent($revision);
            if ($revision->publishAt !== null) return;
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $date = $now;
            if ($when !== '') {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$when,new \DateTimeZone('UTC'));
                if (!$date || $date->format('Y-m-d\TH:i') !== $when || $date <= $now) throw new \DomainException('Indiquez une date UTC future.');
            }
            $revision->publishAt = $date;
            $this->actions->log($actor,'editorial_published','editorial_revision',$revision->id,['code'=>$revision->code,'at'=>$date->format(DATE_ATOM)]);
        });
    }

    private function image(UploadedFile $file): EditorialImage
    {
        if (!$file->isValid() || $file->getSize() > 5_000_000) throw new \DomainException('Image PNG, JPEG ou WebP de 5 Mo maximum attendue.');
        $size = @getimagesize($file->getPathname());
        if (!$size || !in_array($size[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP],true) || $size[0]*$size[1]>8_000_000) {
            throw new \DomainException('Format non pris en charge ou image supérieure à 8 mégapixels.');
        }
        $bitmap = @imagecreatefromstring(file_get_contents($file->getPathname()));
        if (!$bitmap) throw new \DomainException('Image illisible.');
        ob_start();
        try {
            if (!imagewebp($bitmap, null, 85)) throw new \DomainException('La conversion WebP a échoué.');
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $image = new EditorialImage();
        $image->encoded = base64_encode($bytes);
        return $image;
    }
}
