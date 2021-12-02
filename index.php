<?php

$prg_version = "bin2basic v 1.4 standalone";
$LoUp_marker = "upper";  // upper or lower letter
// c64 Vice 2.4 - lower
// Commander X16 - upper
// Yape 1.1.8 - lower  (alt+v)
$max_size = 4096;         // max bytes in file

if (PHP_SAPI <> 'cli') 
{
    echo "\r\nOnly call from command line. Abort program.\r\n";
    exit();
} 

echo "\r\n$prg_version\r\n\e[0;44;42mThis program converts a .prg file (eg compilation result in TASS) \n\rto numbers placed in data lines, for a BASIC loader.              \n\r\e[0m";

## ------------------------------------------------------------------------------------------------
// help or type of basic
    if (isset($argv[1]) || count($argv) <= 1) 
    {
        if (count($argv) == 1) 
            { $type = 'help';}   // if no find arg 1 ->  help
            else
            {$type = $argv[1];}
        
        $basic_type = typeBasic($type);
    }

    echo $basic_type['msg'];
    echo "\r\nSelected basic: " . $basic_type['basic'];
    $LoUp_marker = $basic_type['loup'];
    $basic_type = $basic_type['basic'];


## ------------------------------------------------------------------------------------------------    
    // set input name file
    if (isset($argv[2])) {
        echo "\r\nfile name input: " . $argv[2];
        $file_in = $argv[2];
    } else {
        echo "\e[31m\r\nError.I don't know the name of the input file.\n\r\e[0m";
        exit();
    }
## ------------------------------------------------------------------------------------------------
    // set output name file 
    if (isset($argv[3])) {
        echo "\r\nfile name output: " . $argv[3] . "\r\n";
        $file_out = $argv[3];
    } else {
        echo "\e[31m\r\nError. I don't know the name of the output file. \r\n\e[0m";
        exit();
    }

## ------------------------------------------------------------------------------------------------    
    if (!file_exists($file_in)) {
        echo ("\e[31m\r\nError. Where is my file?\r\n\e[0m");
        exit();
    }

    if (filesize($file_in) >  $max_size) {
        echo "\e[31m\r\nError. This file is too big!\r\n\e[0m";
        exit();
    }

## ------------------------------------------------------------------------------------------------

    $filex = file_get_contents($file_in, true);

    $fileWork = new hex_prg($filex);
    $programHex = $fileWork->programHex();

    $program_hex = $programHex['prghex'];
    $program_bytes = $programHex['prgbytes'];
## ------------------------------------------------------------------------------------------------

    switch ($LoUp_marker) {

        case 'lower':
            $program_hex = strtolower(basic_prg::basic_dec($program_bytes, $basic_type)) . strtolower($program_hex);
            break;
        default:
        case 'upper':
            $program_hex = strtoupper(basic_prg::basic_dec($program_bytes, $basic_type)) . strtoupper($program_hex);
            break;
    }

## ------------------------------------------------------------------------------------------------    

if (!$fd = fopen($file_out, "w")) {
    echo "\r\n\e[31mError. Cannot open file.\e[0m";
    exit();
}

if (!fwrite($fd, $program_hex)) {
    echo "\r\n\e[31mError. Cannot write file.\e[0m";
    exit();
}

## ------------------------------------------------------------------------------------------------

if (file_exists($file_out)) {
    echo "\r\nSuccess.\r\n";
    exit();    
} else {
    echo "\r\n\e[31mError. Something's wrong. File: {$file_out} not exist.\e[0m";
    exit();
}

########################## function #########################################################

function typeBasic(string $type) : array
{
    switch ($type) {
        case 'help':
            echo <<<ENDX
                                ---------------------------------------
                                php index.php c64 file_in file_out
                                ---------------------------------------
                                commands:
                                item 0:
                                php index.php - you know what it is
                                ---------------------------------------
                                item 1:
                                help - display help
                                x16 - basic for Commander x16 (no Commodore c16!!!)
                                plus4 - basic for Commodore+4 (and C16,C116)
                                c64 - basic for c64
                                ---------------------------------------
                                item 2:
                                file_in - source file name
                                item 3:
                                file_out - destination file name 
                    
                                ENDX;
            exit();
            break;

            // set type of basic
        case 'x16':
            $msg = "";            
            $basic_type = "x16emu";
            $LoUp_marker = "upper";
            break;
        case 'plus4':
            $msg = "";            
            $basic_type = "Yape 1.1.8";
            $LoUp_marker = "lower";
            break;
        case 'c64':
            $msg = "";
            $basic_type = "c64 Vice 2.4";
            $LoUp_marker = "lower";
            break;
        default:
            $msg = "\r\n{$argv[1]}\r\nI don't know what it is for\r\ni think you want basic c64.";
            $basic_type = "c64 Vice 2.4";
            $LoUp_marker = "lower";
            break;
    }
    return [    
        'msg'       => $msg,
        'basic'     => $basic_type,
        'loup'      => $LoUp_marker
        ];
}

########################## class ############################################################
// klasa trzymająca skonwertowany bin
class hex_prg
{

    public $file_prg;

    private $program_hex_bytes = 0;
    private $program_hex_index = 0;



    function __construct(string $filex)
    {
        $this->file_prg = bin2hex($filex);
        //  set the number of characters (index) in the file (not HEX numbers!)
        $this->set_size();
    }

    private function set_size(): void
    {
        $this->program_hex_bytes = strlen($this->file_prg);
    }

    // number of characters in the file (number of hex = number of characters / 2)
    private function get_size(): int
    {
        return $this->program_hex_bytes;
    }

    //current reading index
    private function get_index(): int
    {
        return $this->program_hex_index;
    }

    //getting one hex byte (2 characters)
    private function get_byte() : string | bool
    {

        if (isset($this->file_prg[$this->program_hex_index])) {
            $byte_in_hex = $this->file_prg[$this->program_hex_index];
            $this->program_hex_index++;
        } else {
            return false;
        }

        if (isset($this->file_prg[$this->program_hex_index])) {
            $byte_in_hex .= $this->file_prg[$this->program_hex_index];
            $this->program_hex_index++;
        } else {
            return false;
        }

        return $byte_in_hex;
    }

    public function programHex() : array
    {

        $prg_bytes = 0;
        $prg_line = 999;                                      // no first line basic
        $prg_hex = "" . " rem ----------------------------\r\n";
        $prg_line++;
        $prg_hex .= $prg_line . " data " . hexdec($this->get_byte()) . "," . hexdec($this->get_byte());                              // two bytes load adress

        for ($i = 0;; $i++) 
        {
            for ($j = 8; $j > 0; $j--)                            // 8 bytes in line
    
            {
                $hbyte = $this->get_byte();                 // get byte from program and inc index
                if ($hbyte == false) {
                    $hbyte = "end";
                    if ($prg_hex[strlen($prg_hex) - 1] === ",") {
                        $prg_hex = substr($prg_hex, 0, strlen($prg_hex) - 1);  // cut comma after byte
                    }
                    break;
                } else {
    
                    if ($j == 8)                       // insert once in beginning for
                    {
                        $prg_hex .= "\r\n";
                        $prg_line = $prg_line + 10;
                        $prg_hex = $prg_hex . $prg_line . ' data ';
                    }
    
                    if ($j != 1) {
                        $prg_hex = $prg_hex . hexdec($hbyte) . ","; // comma if not end line
                    } else {
                        $prg_hex = $prg_hex . hexdec($hbyte);
                    }
                    $prg_bytes++;
                }
            }
    
            if ($hbyte === "end") {
                $prg_hex .= "\r\n";
                break;
            } else {;
            }                          // nothing for read, exit                   
        }
    
        $prg_hex = $prg_hex . '' . ($prg_line + 10) . ' rem --------- EOF --------------';
    
        $prg_bytes--;
    
        return [
            'prghex' => $prg_hex,
            'prgbytes' => $prg_bytes ];
    }

}

## ------------------------------------------------------------------------------------------------
class basic_prg
{

    // return basic for c64
    public static function basic_dec(int $program_bytes, string $basic_type) : string
    {
        $program_basic = <<<ENDX
                        100 rem {$basic_type}
                        220 gosub 700
                        230 bytes={$program_bytes}
                        240 print"loading...":gosub 800
                        250 end
                        699 rem --- calculate adress --
                        700 read a
                        705 c=a
                        710 read a
                        720 d=a
                        725 adres=(d*256)+c
                        740 return
                        699 rem -------------------------
                        800 for x=0 to bytes
                        810 read a
                        820 poke adres+x,a
                        830 next
                        890 return
                        
                        ENDX;
        return ($program_basic);
    }
}