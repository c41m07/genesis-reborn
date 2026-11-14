<?php
namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GuildImageStorage
{
    public function __construct(#[Autowire('%kernel.project_dir%/var/guild-media')] private string $directory) {}

    public function store(UploadedFile $file,string $kind): string
    {
        [$maxBytes,$maxWidth,$maxHeight]=match($kind){'logo'=>[1048576,512,512],'banner'=>[2097152,1920,640],default=>throw new \DomainException('Type d’image invalide.')};
        if(!$file->isValid() || ($file->getSize()??0)<1 || $file->getSize()>$maxBytes) throw new \DomainException('Image invalide ou trop volumineuse : logo 1 Mo, bannière 2 Mo maximum.');
        $size=@getimagesize($file->getPathname());
        $formats=[IMAGETYPE_JPEG=>['image/jpeg','jpg'],IMAGETYPE_PNG=>['image/png','png'],IMAGETYPE_WEBP=>['image/webp','webp']];
        if(!$size || !isset($formats[$size[2]]) || $file->getMimeType()!==$formats[$size[2]][0]) throw new \DomainException('Utilisez une image JPEG, PNG ou WebP valide. Les SVG sont refusés.');
        if($size[0]<1 || $size[1]<1 || $size[0]>$maxWidth || $size[1]>$maxHeight) throw new \DomainException('Dimensions maximales : logo 512 × 512 pixels, bannière 1 920 × 640 pixels.');
        $image=@imagecreatefromstring(file_get_contents($file->getPathname()));
        if(!$image) throw new \DomainException('Cette image ne peut pas être décodée. Choisissez un autre fichier.');
        $filename=bin2hex(random_bytes(24)).'.'.$formats[$size[2]][1];$path=$this->directory.'/'.$filename;
        try {
            if(!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new \RuntimeException('Impossible de préparer le stockage des images de guilde.');
            imagesavealpha($image,true);
            $saved=match($size[2]){IMAGETYPE_JPEG=>imagejpeg($image,$path,85),IMAGETYPE_PNG=>imagepng($image,$path,9),IMAGETYPE_WEBP=>imagewebp($image,$path,85)};
            if(!$saved || !is_file($path) || filesize($path)<1) throw new \RuntimeException('Impossible d’enregistrer l’image de guilde.');
            if(filesize($path)>$maxBytes) throw new \DomainException('L’image réencodée est trop volumineuse. Choisissez une image plus petite.');
            chmod($path,0600);
            return $filename;
        } catch(\Throwable $error) {
            $this->discard($filename);throw $error;
        } finally { imagedestroy($image); }
    }

    public function path(string $filename): string
    {
        if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$filename)) throw new \DomainException('Image inaccessible.');
        return $this->directory.'/'.$filename;
    }

    public function mime(string $filename): string
    {
        $this->path($filename);
        return match(pathinfo($filename,PATHINFO_EXTENSION)){'jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'};
    }

    public function discard(?string $filename): void
    {
        if($filename===null) return;
        $path=$this->path($filename);
        if(is_file($path)) @unlink($path);
    }
}
