<?php
namespace App\Tests\Service;

use App\Service\Player\GuildImageStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class GuildImageStorageTest extends TestCase
{
    private string $directory;
    private GuildImageStorage $storage;

    protected function setUp(): void
    {
        $this->directory=sys_get_temp_dir().'/genesis-guild-test-'.bin2hex(random_bytes(8));mkdir($this->directory,0700);
        $this->storage=new GuildImageStorage($this->directory.'/stored');
    }

    protected function tearDown(): void
    {
        foreach(glob($this->directory.'/stored/*')?:[] as $file) unlink($file);
        if(is_dir($this->directory.'/stored')) rmdir($this->directory.'/stored');
        foreach(glob($this->directory.'/*')?:[] as $file) unlink($file);
        rmdir($this->directory);
    }

    private function upload(string $bytes,string $name='../../unsafe.php'): UploadedFile
    {
        $path=$this->directory.'/input-'.bin2hex(random_bytes(4));file_put_contents($path,$bytes);
        return new UploadedFile($path,$name,'application/x-php',null,true);
    }

    private function image(string $format,int $width=16,int $height=16): string
    {
        $image=imagecreatetruecolor($width,$height);ob_start();
        match($format){'png'=>imagepng($image),'jpg'=>imagejpeg($image),'webp'=>imagewebp($image)};
        $bytes=ob_get_clean();imagedestroy($image);return $bytes;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('formats')]
    public function testActualFormatIsDecodedAndReencodedWithGeneratedPrivateName(string $format,string $mime): void
    {
        $bytes=$this->image($format);
        if($format==='jpg') {$metadata='GPS-METADATA-PRIVATE';$bytes=substr($bytes,0,2)."\xff\xfe".pack('n',strlen($metadata)+2).$metadata.substr($bytes,2);}
        $bytes.='<?php PRIVATE-PAYLOAD ?>';
        $filename=$this->storage->store($this->upload($bytes),'logo');$path=$this->storage->path($filename);
        self::assertMatchesRegularExpression('/^[a-f0-9]{48}\\.'.preg_quote($format,'/').'$/',$filename);
        self::assertSame($mime,$this->storage->mime($filename));self::assertSame($mime,(new \finfo(FILEINFO_MIME_TYPE))->file($path));
        self::assertSame([16,16],array_slice(getimagesize($path),0,2));
        self::assertStringNotContainsString('PRIVATE-PAYLOAD',file_get_contents($path));self::assertStringNotContainsString('GPS-METADATA-PRIVATE',file_get_contents($path));
        self::assertSame(0600,fileperms($path)&0777);
        $this->storage->discard($filename);self::assertFileDoesNotExist($path);
    }
    public static function formats(): array {return [['png','image/png'],['jpg','image/jpeg'],['webp','image/webp']];}

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFiles')]
    public function testInvalidFilesNeverCreateStoredMedia(string $kind,string $scenario): void
    {
        $bytes=match($scenario){
            'svg'=>'<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>',
            'html'=>'<html><script>alert(1)</script></html>','broken'=>"\x89PNG\r\n\x1a\n",
            'size'=>str_repeat('x',($kind==='logo'?1048576:2097152)+1),
            'width'=>$this->image('png',$kind==='logo'?513:1921,1),
            'height'=>$this->image('png',1,$kind==='logo'?513:641),
        };
        $this->expectException(\DomainException::class);
        try {$this->storage->store($this->upload($bytes,'image.png'),$kind);}
        finally {self::assertSame([],glob($this->directory.'/stored/*')?:[]);}
    }
    public static function invalidFiles(): array
    {
        return array_merge(array_map(fn($case)=>['logo',$case],['svg','html','broken','size','width','height']),array_map(fn($case)=>['banner',$case],['size','width','height']));
    }

    public function testPathTraversalIsRejected(): void
    {
        $this->expectException(\DomainException::class);$this->storage->path('../private.env');
    }
}
