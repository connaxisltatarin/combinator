<?php 
namespace Combinator\View\Helper;

use Cake\View\Helper;
use Cake\View\View;
use Cake\Core\Configure;
use Combinator\Lib\JSMin;
use Combinator\Lib\CssMin;

class CombinatorHelper extends Helper{
    var $libs = array('js' => array(), 'css' => array());
	var $uncompressed_libs = array('js' => array(), 'css' => array());
    var $inline_code = array('js' => array(), 'css' => array());
    var $basePath = null;
    var $cachePath = null;

    // default conf
    private $__options = array(
        'js' => array(
            'path' => '/js',
            'cachePath' => '/assets',
            'enableCompression' => true
        ),
        'css' => array(
            'absolutePaths' => true,
            'path' => '/css',
            'cachePath' => '/assets',
            'enableCompression' => true,
            'compression' => 'high_compression' // Can be "high_compression", "highest_compression", "low_compression", or "default"
        )
    );

    function __construct(View $View, $options = []) {
        $this->__options['js'] = !empty($options['js']) ? array_merge($this->__options['js'], $options['js']) : $this->__options['js'];
        $this->__options['css'] = !empty($options['css']) ? array_merge($this->__options['css'], $options['css']) : $this->__options['css'];

        $this->__options['js']['path'] = $this->clean_path($this->__options['js']['path']);
        $this->__options['js']['cachePath'] = $this->clean_path($this->__options['js']['cachePath']);
        $this->__options['css']['path'] = $this->clean_path($this->__options['css']['path']);
        $this->__options['css']['cachePath'] = $this->clean_path($this->__options['css']['cachePath']);

        $this->basePath['js'] = WWW_ROOT.$this->__options['js']['path'];
        $this->cachePath['js'] = WWW_ROOT.$this->__options['js']['cachePath'];
        $this->basePath['css'] = WWW_ROOT.$this->__options['css']['path'];
        $this->cachePath['css'] = WWW_ROOT.$this->__options['css']['cachePath'];
		
		$this->webroot = $View->request->webroot;
    }
    
    /**
    * Separa los files que no se tienen q comprimir
    * 
    * @param mixed $type
    */
    function separate_arrays($type){
        $to_compress = array();
        $this->uncompressed_libs[$type]['top'] = array();
        $this->uncompressed_libs[$type]['bottom'] = array();
        
        foreach($this->libs[$type] as $i => $file){
            if(is_array($file)){
                if(isset($file[1]['position'])){
                    $this->uncompressed_libs[$type][$file[1]['position']][] = $file[0];
                }else{
                    $this->uncompressed_libs[$type]['bottom'][] = $file[0];
                }
            }else{
                $to_compress[] = $file;
            }
        }
        
        $this->libs[$type] = $to_compress;
    }

    /**
    * Reemplaza los arrays de file por el path del file
    * 
    * @param mixed $type
    */
    function replace_arrays($type){
        foreach($this->libs[$type] as $i => $file){
            if(is_array($file)){
                $this->libs[$type][$i] = $file[0];
            }
        }
    }

    function scripts($type) {
        switch($type) {
            case 'js':
                if(Configure::read('Optimization.minifyJs')){
                    $this->separate_arrays('js');
                    $cachefile_js = $this->generate_filename('js');
                    
                    $jsFiles = '';
                    foreach($this->uncompressed_libs['js']['top'] as $jsFile){
                        $path = str_replace('//', '/', $this->webroot.$this->clean_lib_list($jsFile, 'js'));
                        $jsFiles .= '<script src="'.$path.'" type="text/javascript"></script>
    ';
                    }

                    $jsFiles .= $this->get_js_html($cachefile_js);

                    foreach($this->uncompressed_libs['js']['bottom'] as $jsFile){
                        $path = str_replace('//', '/', $this->webroot.$this->clean_lib_list($jsFile, 'js'));
                        $jsFiles .= '<script src="'.$path.'" type="text/javascript"></script>
    ';
                    }
                    
                    return $jsFiles;
                }else{
                    $this->replace_arrays('js');
                    $jsFiles = '';
                    foreach($this->libs['js'] as $jsFile){
                    	if(substr($jsFile, -3, 3) == '.js') {
							$jsFile = substr($jsFile, 0, -3);
						}
						
						$jsFile = substr($jsFile, 3);
						
						// Fix for LT1107
						// TinyMCE doesn't like to be renamed
						// So if the file is tiny_mce.js we do not add _versionNNNNNNNN
						if ($jsFile === "/site/tiny_mce/tiny_mce") {
							$jsFiles .= '<script src="'.$this->webroot.$this->__options['js']['path'].$this->clean_lib_list($jsFile.'.js', 'js').'" type="text/javascript"></script>';
						} else {
							$jsFiles .= '<script src="'.$this->webroot.$this->__options['js']['path'].$this->clean_lib_list($jsFile.'_version'.filemtime($this->basePath[$type].$jsFile.'.js').'.js', 'js').'" type="text/javascript"></script>';
						}
                    }
					
                    return $jsFiles;
                }
            break;
            case 'css':
                if(Configure::read('Optimization.minifyCss')){
                    $this->separate_arrays('css');
                    $cachefile_css = $this->generate_filename('css');
                    
                    $cssFiles = '';
                    foreach($this->uncompressed_libs['css']['top'] as $cssFile){
                        $path = str_replace('//', '/', $this->webroot.$this->clean_lib_list($cssFile, 'css'));
                        $cssFiles .= '<link href="'.$path.'" rel="stylesheet" type="text/css" />
    ';
                    }
                    
                    $cssFiles .= $this->get_css_html($cachefile_css);

                    foreach($this->uncompressed_libs['css']['bottom'] as $cssFile){
                        $path = str_replace('//', '/', $this->webroot.$this->clean_lib_list($cssFile, 'css'));
                        $cssFiles .= '<link href="'.$path.'" rel="stylesheet" type="text/css" />
    ';
                    }
                    
                    return $cssFiles;
                }else{
                    $this->replace_arrays('css');
                    $cssFiles = '';
                    foreach($this->libs['css'] as $cssFile){
                       if(substr($cssFile, -4, 4) == '.css') {
							$cssFile = substr($cssFile, 0, -4);
						}
						
						$cssFile = substr($cssFile, 4);
						
                		$cssFiles .= '<link href="'.$this->webroot.$this->__options['css']['path'].$this->clean_lib_list($cssFile.'_version'.filemtime($this->basePath[$type].DS.$cssFile.'.css').'.css', 'css').'" rel="stylesheet" type="text/css" />';
                    }
                    return $cssFiles;
                }
            break;
        }
    }

    private function generate_filename($type) {
        $this->libs[$type] = array_unique($this->libs[$type]);

        // Create cache folder if not exist
        if(!file_exists($this->cachePath[$type])) {
            mkdir($this->cachePath[$type]);
        }

        // Define last modified to refresh cache if needed
        $lastmodified = 0;
        foreach($this->libs[$type] as $key => $lib) {
            $lib = $this->clean_lib_list($lib, $type);
            if(file_exists(WWW_ROOT.$lib)) {
                $lastmodified = max($lastmodified, filemtime(WWW_ROOT.$lib));
            }
            $this->libs[$type][$key] = $lib;
        }
        $hash = $lastmodified.'-'.md5(serialize($this->libs[$type]).'_'.serialize($this->inline_code[$type]));
        return 'cache-'.$hash.'.'.$type;
    }

    private function get_js_html($cachefile) {
        if(file_exists($this->cachePath['js'].'/'.$cachefile)) {
            return '<script src="'.(Configure::read('AmazonS3.status') ? Configure::read('AmazonS3.publicUrl').'/' : $this->webroot).$this->__options['js']['cachePath'].'/'.$cachefile.'" type="text/javascript"></script>';
        }
        // Get the content
        $file_content = '';
        foreach($this->libs['js'] as $lib) {
            $file_content .= "\n\n".file_get_contents(substr($lib, 1, strlen($lib) - 1)).';';
        }

        // If compression is enable, compress it !
        if($this->__options['js']['enableCompression']) {
            $file_content = trim(JSMin::minify($file_content));
        }

        // Get inline code if exist
        // Do it after jsmin to preserve variable's names
        if(!empty($this->inline_code['js'])) {
            foreach($this->inline_code['js'] as $inlineJs) {
                $file_content .= "\n\n".$inlineJs;
            }
        }
		
        if($fp = fopen($this->cachePath['js'].'/'.$cachefile, 'wb')) {
            fwrite($fp, $file_content);
            fclose($fp);
			
			if(Configure::read('AmazonS3.status_sync')){
				App::import('Component', 'ComponentLoader');
				App::import('Core', 'Controller');

				if(!isset($this->controller)){
					$this->controller = new Controller();
				}
				$this->controller->ComponentLoader = new ComponentLoaderComponent();
				$this->controller->ComponentLoader->initialize($this->controller);
				
				$folderS3remote = $this->__options['js']['cachePath'].DS;
				
				$this->controller->ComponentLoader->load('AmazonS3.AmazonS3');
				$this->controller->AmazonS3->local_dir = WWW_ROOT; 
				$this->controller->AmazonS3->local_object = $folderS3remote.$cachefile; 
				
				if(!$this->controller->AmazonS3->put('private', 'text/js')) {
					$this->log($this->controller->AmazonS3->local_object, 'error_amazonS3');
					$this->log($this->controller->AmazonS3->errors, 'error_amazonS3');
				}								
			}			
        }
        return '<script src="'.(Configure::read('AmazonS3.status') ? Configure::read('AmazonS3.publicUrl').'/' : $this->webroot).$this->__options['js']['cachePath'].'/'.$cachefile.'" type="text/javascript"></script>';
    }

    private function get_css_html($cachefile) {
        if(file_exists($this->cachePath['css'].'/'.$cachefile)) {
            return '<link href="'.(Configure::read('AmazonS3.status') ? Configure::read('AmazonS3.publicUrl').'/' : $this->webroot).$this->__options['css']['cachePath'].'/'.$cachefile.'" rel="stylesheet" type="text/css" />';
        }
        // Get the content
        $file_content = '';
        foreach($this->libs['css'] as $lib) {
            $content = "\n\n".file_get_contents(substr($lib, 1, strlen($lib) - 1));
            
            if($this->__options['css']['absolutePaths']) {
                // busco las urls de imagenes
                preg_match_all('/url\(([^)]+)\)/', $content, $matches);
                if($matches[1]){
                    // path del css
                    $path_info = pathinfo(WWW_ROOT.$lib);
                    foreach($matches[1] as $i => $_url){
                        $url = $_url;
                            // si es data no hay path
                        if(strpos($url, 'data:image') === false){
                            // le saco las comillas
                            $url = str_replace(array('\'', '"'), '', $url);
                            // si es url absoluta no la cambio
                            if($url[0] != '/'){
                                // convierto la url en path
                                $url = str_replace('/', DS, $url);
                                $full_path = $path_info['dirname'].DS.$url;
                                $full_path = realpath($full_path);
                                $root_url = '';
                                if($full_path){
                                    $root_path = DS.str_replace(WWW_ROOT, '', $full_path);
                                    $root_url = str_replace(DS, '/', $root_path);
                                    $root_url = str_replace('//', '/', $this->webroot.$root_url);
                                    // reemplazo la url relativa por la absoluta
                                    $content = str_replace($matches[0][$i], 'url('.$root_url.')', $content);
                                }
                            }
                        }
                    }
                }            
            }
            
            $file_content .= $content;
        }
        
        // Get inline code if exist
        if(!empty($this->inline_code['css'])) {
            foreach($this->inline_code['css'] as $inlineCss) {
                $file_content .= "\n\n".$inlineCss;
            }
        }

        // If compression is enable, compress it !
        if($this->__options['css']['enableCompression']) {
            $file_content = trim(CssMin::minify($file_content));
        }

        if($fp = fopen($this->cachePath['css'].'/'.$cachefile, 'wb')) {
            fwrite($fp, $file_content);
            fclose($fp);
			
			if(Configure::read('AmazonS3.status_sync')){
				App::import('Component', 'ComponentLoader');
				App::import('Core', 'Controller');

				if(!isset($this->controller)){
					$this->controller = new Controller();
				}
				$this->controller->ComponentLoader = new ComponentLoaderComponent();
				$this->controller->ComponentLoader->initialize($this->controller);
				
				$folderS3remote = $this->__options['css']['cachePath'].DS;
				
				$this->controller->ComponentLoader->load('AmazonS3.AmazonS3');
				$this->controller->AmazonS3->local_dir = WWW_ROOT; 
				$this->controller->AmazonS3->local_object = $folderS3remote.$cachefile; 
				
				if(!$this->controller->AmazonS3->put('private', 'text/css')) {
					$this->log($this->controller->AmazonS3->local_object, 'error_amazonS3');
					$this->log($this->controller->AmazonS3->errors, 'error_amazonS3');
				}								
			}
        }

        return '<link href="'.(Configure::read('AmazonS3.status') ? Configure::read('AmazonS3.publicUrl').'/' : $this->webroot).$this->__options['css']['cachePath'].'/'.$cachefile.'" rel="stylesheet" type="text/css" />';
    }

    function add_libs($type, $libs) {
        switch($type) {
            case 'js':
                $bkp = $this->libs[$type];
                $this->libs[$type] = array();
                if(is_array($libs)) {
                    foreach($libs as $lib) {
                        $this->libs[$type][] = $this->relativePathToAbsolute($this->basePath['js'], $lib);
                    }
                } else {
                    $this->libs[$type][] = $this->relativePathToAbsolute($this->basePath['js'], $libs);
                }
                foreach($bkp as $e) {
                    $this->libs[$type][] = $e;
                }
            break;
            case 'css':
                if(is_array($libs)) {
                    foreach($libs as $lib) {
                        $this->libs[$type][] = $this->relativePathToAbsolute($this->basePath['css'], $lib);
                    }
                }else {
                    $this->libs[$type][] = $this->relativePathToAbsolute($this->basePath['css'], $libs);
                }
            break;
        }
    }
    
    function relativePathToAbsolute($base, $path){
        $original = $path;
        if(is_array($path)){
            $path = $path[0];
        }
        
        if($path[0] != '/'){
            // convierto la url en path
            $path = str_replace('/', DS, $path);
            $full_path = $base.DS.$path;
            $full_path = realpath($full_path);
            if($full_path){
                $root_path = DS.str_replace(WWW_ROOT, '', $full_path);
                $path = str_replace(DS, '/', $root_path);
            }
        }
        
        if(is_array($original)){
            $original[0] = $path;
            
            return $original;
        }
        
        return $path;
    }
    
    function clean_libs($type) {
        switch($type) {
            case 'js':
            case 'css':
                $this->libs[$type] = array();
        }
    }

    function add_inline_code($type, $codes) {
        switch($type) {
            case 'js':
            case 'css':
                if(is_array($codes)) {
                    foreach($codes as $code) {
                        $this->inline_code[$type][] = $code;
                    }
                }else {
                    $this->inline_code[$type][] = $codes;
                }
                break;
        }
    }

    private function clean_lib_list($filename, $type) {
        if (strpos($filename, '?') === false) {
            if (strpos($filename, '.'.$type) === false) {
                $filename .= '.'.$type;
            }
        }

        return $filename;
    }

    private function clean_path($path) {
        // delete the / at the end of the path
        $len = strlen($path);
        if(strrpos($path, '/') == ($len - 1)) {
            $path = substr($path, 0, $len - 1);
        }

        // delete the / at the start of the path
        if(strpos($path, '/') == '0') {
            $path = substr($path, 1, $len);
        }
        return $path;
    }
    
    function clearCache(){
        foreach($this->cachePath as $path){
            if(substr($path, strlen($path)-1, 1) != DS){
                $path = $path.DS;
            }
            
            if (is_dir($path)) {
                $files = glob($path . '*');

                if ($files === false) {
                    return false;
                }

                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
    }
}
