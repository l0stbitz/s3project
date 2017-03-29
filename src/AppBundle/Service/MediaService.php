<?php
namespace AppBundle\Service;

use Carbon\Carbon;
//use GuzzleHttp\Client;
//use GuzzleHttp\Cookie\CookieJar;
use Aws\S3\S3Client;
use Imagick;
use ImagickPixel;
use EmpireBundle\Utility\MimeType;
use EmpireBundle\Service\BaseService;

class MediaService
{

    public function __construct($container)
    {
        $this->em = $container->get('doctrine')->getManager();
        //$this->client = new Client();
        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'credentials' => [
                'key' => $container->getParameter('amazon_aws_key'),
                'secret' => $container->getParameter('amazon_aws_secret_key'),
            ]
        ]);
        $this->bucket = $container->getParameter('amazon_s3_bucket_name');
        $this->logger = $container->get('logger');
    }

    /**
     *
     * @param type $filename
     * @param type $file
     * @param type $ext
     * @return boolean
     */
    public function uploadImage($filename, $file, $ext, $mime = null)
    {
        try {
            $this->s3->putObject([
                'Bucket' => $this->bucket,
                'Key' => $filename,
                'Body' => $file,
                'ACL' => 'public-read',
                'ContentType' => $mime != null ? $mime : MimeType::get_mimetype($ext),
            ]);
            return true;
        } catch (Aws\Exception\S3Exception $e) {
            // $this->view['valid'] = "There was an error uploading the file";
        }
        return false;
    }

    /**
     *
     */
    public function doesFileExist($filename)
    {
        $exist = $this->s3->doesObjectExist($this->bucket, $filename);

        return $exist;
    }

    public function getImageMimeInfo($imageString)
    {
        $imageData = getimagesizefromstring($imageString);

        if ($imageData === false) {
            // this is probably not an image if this is false
            return [false, false];
        }

        $fileExt = '.jpg';
        // images are always converted to jpg unless specifically an animated gif
        if ($imageData['mime'] == 'image/gif') {
            $fileExt = '.gif';
        } elseif ($imageData['mime'] == 'image/png') {
            $imagick = new Imagick();
            $imagick->readImageBlob($imageString);

            // flatten png so transparency works
            $flat = new Imagick();
            $flat->newImage($imagick->getImageWidth(), $imagick->getImageHeight(), new ImagickPixel('white'));
            $flat->compositeImage($imagick, Imagick::COMPOSITE_OVER, 0, 0);
            $flat->setImageFormat('jpeg');
            $flat->setImageCompressionQuality(90);
            $imageString = $flat->getImageBlob();
            $imageData = getimagesizefromstring($imageString);
        } elseif (strpos($imageData['mime'], 'image/') == false) {
            $imagick = new Imagick();
            $imagick->readImageBlob($imageString);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(90);
            $imageString = $imagick->getImageBlob();
            $imageData = getimagesizefromstring($imageString);
        }

        $imageInfo = [
            'data' => $imageData,
            'ext' => $fileExt
        ];

        return [$imageInfo, $imageString];
    }

    public function getImageFromUrl($imageUrl)
    {
        $client = new Client();
        $response = $client->get($imageUrl);

        $imageString = $response->getBody()->getContents();

        return $imageString;
    }

    public function searchBucket($start, $limit, $search, $order, $columns)
    {
        $res = [];
        $res['data'] = [];
        $res['recordsTotal'] = 0;
        $res['recordsFiltered'] = 0;
        $results = $this->s3->getIterator('ListObjects', ['Bucket' => '99s3'], array(
            'limit' => 10,
            'page_size' => 10
        ));
        foreach($results as $result){
            $res['data'][] = $result;
        }
        $res['recordsTotal'] = count($res['data']);
        return $res;
    }
}
