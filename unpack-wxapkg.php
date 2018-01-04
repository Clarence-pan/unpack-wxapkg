<?php


function unpack_wxapkg($file, $targetDir)
{
    if (!is_dir($targetDir)){
        mkdir($targetDir);
    }

    echo "Reading file.\n";
    $file = file_get_contents($file);
    $ptr = 18;

    $headerStruct = new StructDef([
        'mask1' => 'ushort',
        'info1' => 'ulong',
        'indexInfoLength' => 'ulong',
        'bodyInfoLength' => 'ushort',
        'mask2' => 'ushort',
        'fileCount' => 'ulong',
    ]);

    echo "Parsing file header...\n";

    $header = $headerStruct->unpack($file);
//    print_r(['header' => $header]);

    $unpackULong = function () use (&$file, &$ptr) {
        $ret = unpack_ulong(substr($file, $ptr, 4));
        $ptr += 4;
        return $ret;
    };

    $unpackUShort = function () use (&$file, &$ptr) {
        $ret = unpack_ushort(substr($file, $ptr, 2));
        $ptr += 2;
        return $ret;
    };


    $unpackStr = function ($len) use (&$file, &$ptr) {
        $ret = substr($file, $ptr, $len);
        $ptr += $len;
        return $ret;
    };


    $fileCount = $header['fileCount'];

    echo "Got $fileCount files.\n";

    $unpackedFiles = [];

    for ($i = 0; $i < $fileCount; $i++) {
        $nameLength = $unpackULong();
        $f = [
            'nameLength' => $nameLength,
            'name' => $unpackStr($nameLength),
            'offset' => $unpackULong(),
            'size' => $unpackULong(),
        ];

        echo "Unpacking file {$f['name']} ({$f['size']}bytes)...\n";

        $f['content'] = substr($file, $f['offset'], $f['size']);
        $unpackedFiles[] = $f;

        $destFile = $targetDir . $f['name'];
        $destDir = dirname($destFile);
        if (!is_dir($destDir)){
            mkdir($destDir, 0777, true);
        }

        file_put_contents($targetDir . $f['name'], $f['content']);
    }


//    print_r(['unpackedFiles' => $unpackedFiles]);



    echo "All done.\n";
}

function unpack_ulong($str)
{
    $x = unpack('N', $str);
    return $x[1];
}

function unpack_ushort($str)
{
    $x = unpack('n', $str);
    return $x[1];
}

class StructDef
{
    protected $def;
    protected $unpackFormat;

    public function __construct($def)
    {
        $this->def = $def;
        $this->unpackFormat = self::convertStructDefToUnpackFormat($def);
    }

    public function unpack($data)
    {
        return unpack($this->unpackFormat, $data);
    }

    protected static function convertStructDefToUnpackFormat($def)
    {
        $defTypeToUnpackType = [
            'byte' => 'C',
            'uchar' => 'C',
            'u8' => 'C',
            'ushort' => 'n',
            'u16' => 'n',
            'ulong' => 'N',
            'u32' => 'N',
        ];

        $ret = [];
        foreach ($def as $key => $type) {
            $ret[] = $defTypeToUnpackType[$type] . $key;
        }

        return implode('/', $ret);
    }
}

$packageFile = $argv[1];

//支持目录下文件批量解压
 if (is_dir($packageFile)){
    $handle = opendir($packageFile);
    if($handle){
        while(($fl = readdir($handle)) !== false){
            $temp = $packageFile.DIRECTORY_SEPARATOR.$fl;
            //如果不加  $fl!='.' && $fl != '..'  则会造成把$dir的父级目录也读取出来
            if(is_file($temp)){
                if($fl!='.' && $fl != '..'){
                    $targetDir = $temp . '.unpacked';
                    unpack_wxapkg($temp, $targetDir);
                }
            }
        }
    }  
}else if (is_file($packageFile)){
    $targetDir = $packageFile . '.unpacked';
    unpack_wxapkg($packageFile, $targetDir);
}else{
    echo <<<HELP
Usage:
    [php] {$argv[0]} <xxx.wxapkg>

    - Unpack the `xxx.wxapkg` to `xxx.wxapkg.unpacked` directory.

HELP;

    exit(1);
}

exit(0);

