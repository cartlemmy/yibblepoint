<?php

class slVideo {
	private $file = false;
	public $previewDir;
	
	public $width = 0;
	public $height = 0;
	
	public $prevIm = null;
	public $previewFile = false;
	private $prevW = 0;
	private $prevH = 0;
	
	private $imDir = false;
	public $info = null;

	function __construct($file, $previewDir = false) {
		$this->previewDir = $previewDir ? $previewDir : SL_DATA_PATH."/vid-preview";
		if (!is_dir($this->previewDir)) mkdir($this->previewDir);
		
		$this->file = $file;
		if (!$md5 = quick_md5_file($file)) return;
		
		$this->imDir = '/tmp/'.$md5;
		
		ob_start();
		system('ffprobe -of json -show_format -loglevel quiet '.escapeshellarg($file), $out);
		$this->info = json_decode(ob_get_clean(),true);
		
		if (!is_dir($this->imDir)) {
			mkdir($this->imDir);
			chmod($this->imDir, 0777);
		
			system('ffmpeg -ss 1 -i '.escapeshellarg($file).' -f image2 -r 2 -t 30 '.escapeshellarg($this->imDir.'/%03d.jpg'));
		}
		
		$im = getimagesize($this->imDir.'/001.jpg');
		$this->width = $im[0];
		$this->height = $im[1];
		
		if ($this->width > $this->height) {
			$this->prevW = 256;
			$this->prevH = round(($this->height / $this->width) * 256);
		} else {
			$this->prevW = round(($this->width / $this->height) * 256);
			$this->prevH = 256;
		}
				
		$this->previewFile = $this->previewDir.'/'.substr($md5,0,2);
		if (!is_dir($this->previewFile)) mkdir($this->previewFile);
		$this->previewFile .= '/'.substr($md5,2).".jpg";
		
		if (is_file($this->previewFile)) {
			$this->prevIm = new slImage();
			$this->prevIm->fromFile($this->previewFile);
		} else {
			for ($frames = 0; $frames < 60; $frames ++) {
				if (!is_file($this->imDir.'/'.sprintf('%03d',$frames + 1).'.jpg')) break;	
			}
			$this->prevIm = new slImage($this->prevW * 5, $this->prevH * ceil($frames / 5));
			$frame = 0;
			for ($y = 0; $y < 12; $y++) {
				for ($x = 0; $x < 5; $x++) {
					$frFile = $this->imDir.'/'.sprintf('%03d',$frame + 1).'.jpg';
					if (!is_file($frFile)) break;
					$frim = new slImage();
					$frim->fromFile($frFile);
					$frim->resize($this->prevW, $this->prevH);
					$this->prevIm->copy($frim, $x * $this->prevW, $y * $this->prevH, 0, 0, $this->prevW, $this->prevH);
					$frame++;
				}
			}
			
			$this->prevIm->jpeg($this->previewFile);
			system('rm -rf '.escapeshellarg($this->imDir));
		}		
	}
}
