<?php
/**
 * Simple PDF Generator
 * 
 * Creates valid PDF 1.4 documents using pure PHP.
 * 
 * @package FRA_Member_Tools
 */

if (!defined('ABSPATH')) {
    exit;
}

class FRAMT_Simple_PDF {
    
    private $buffer = '';
    private $page_content = '';
    private $offsets = array();
    private $n = 0;
    private $font_size = 12;
    private $y = 720; // Start position (792 - 72 margin)
    private $line_height = 16;
    private $left_margin = 72;
    private $right_margin = 540; // 612 - 72
    private $page_width = 612;
    
    public function __construct() {
        $this->buffer = '';
    }
    
    public function addPage() {
        $this->y = 720;
        $this->page_content = '';
    }
    
    public function setFontSize($size) {
        $this->font_size = $size;
        $this->line_height = $size + 4;
    }
    
    public function writeTitle($text) {
        $old_size = $this->font_size;
        $this->setFontSize(16);
        $this->writeLine($text, true, true);
        $this->y -= 10;
        $this->setFontSize($old_size);
    }
    
    public function write($text, $bold = false) {
        // Word wrap
        $words = explode(' ', $text);
        $line = '';
        $char_width = $this->font_size * 0.5;
        $max_width = $this->right_margin - $this->left_margin;
        
        foreach ($words as $word) {
            $test_line = $line . ($line ? ' ' : '') . $word;
            $test_width = strlen($test_line) * $char_width;
            
            if ($test_width > $max_width && $line) {
                $this->writeLine($line, $bold);
                $line = $word;
            } else {
                $line = $test_line;
            }
        }
        
        if ($line) {
            $this->writeLine($line, $bold);
        }
    }
    
    private function writeLine($text, $bold = false, $center = false) {
        if ($this->y < 72) {
            // Would go off page - in production, would add new page
            return;
        }
        
        $x = $this->left_margin;
        if ($center) {
            $text_width = strlen($text) * $this->font_size * 0.5;
            $x = ($this->page_width - $text_width) / 2;
        }
        
        $font = $bold ? 'F2' : 'F1';
        $text = $this->escape($text);
        
        $this->page_content .= sprintf(
            "BT /%s %d Tf %d %d Td (%s) Tj ET\n",
            $font,
            $this->font_size,
            (int)$x,
            (int)$this->y,
            $text
        );
        
        $this->y -= $this->line_height;
    }
    
    public function addSpace($lines = 1) {
        $this->y -= $this->line_height * $lines;
    }
    
    private function escape($text) {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        $text = str_replace("\r", '', $text);
        $text = str_replace("\n", '', $text);
        // Remove any non-ASCII characters
        $text = preg_replace('/[^\x20-\x7E]/', '', $text);
        return $text;
    }
    
    private function newObj() {
        $this->n++;
        $this->offsets[$this->n] = strlen($this->buffer);
        $this->buffer .= $this->n . " 0 obj\n";
        return $this->n;
    }
    
    private function endObj() {
        $this->buffer .= "endobj\n";
    }
    
    public function output() {
        // Reset
        $this->buffer = '';
        $this->n = 0;
        $this->offsets = array();
        
        // Header
        $this->buffer .= "%PDF-1.4\n";
        $this->buffer .= "%\xE2\xE3\xCF\xD3\n"; // Binary marker
        
        // Font 1: Helvetica
        $this->newObj();
        $this->buffer .= "<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>\n";
        $this->endObj();
        
        // Font 2: Helvetica-Bold
        $this->newObj();
        $this->buffer .= "<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold/Encoding/WinAnsiEncoding>>\n";
        $this->endObj();
        
        // Resources dictionary
        $this->newObj();
        $this->buffer .= "<</Font<</F1 1 0 R/F2 2 0 R>>>>\n";
        $this->endObj();
        
        // Page content stream
        $this->newObj();
        $stream = $this->page_content;
        $this->buffer .= "<</Length " . strlen($stream) . ">>\n";
        $this->buffer .= "stream\n";
        $this->buffer .= $stream;
        $this->buffer .= "endstream\n";
        $this->endObj();
        
        // Page
        $this->newObj();
        $this->buffer .= "<</Type/Page/Parent 6 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources 3 0 R>>\n";
        $this->endObj();
        
        // Pages
        $this->newObj();
        $this->buffer .= "<</Type/Pages/Kids[5 0 R]/Count 1>>\n";
        $this->endObj();
        
        // Catalog
        $this->newObj();
        $this->buffer .= "<</Type/Catalog/Pages 6 0 R>>\n";
        $this->endObj();
        
        // Cross-reference table
        $xref_offset = strlen($this->buffer);
        $this->buffer .= "xref\n";
        $this->buffer .= "0 " . ($this->n + 1) . "\n";
        $this->buffer .= "0000000000 65535 f \n";
        
        for ($i = 1; $i <= $this->n; $i++) {
            $this->buffer .= sprintf("%010d 00000 n \n", $this->offsets[$i]);
        }
        
        // Trailer
        $this->buffer .= "trailer\n";
        $this->buffer .= "<</Size " . ($this->n + 1) . "/Root 7 0 R>>\n";
        $this->buffer .= "startxref\n";
        $this->buffer .= $xref_offset . "\n";
        $this->buffer .= "%%EOF";
        
        return $this->buffer;
    }
    
    public function save($filepath) {
        $content = $this->output();
        return file_put_contents($filepath, $content) !== false;
    }
}
