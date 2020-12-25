<?php
	
	class MagickCss{
		
		private $cssData = array(),
		$config = array(
		'minify'=>true,
		'remove_duplicate'=>true,
		'remove_mediaquery'=>false,
		'vendor_prefix'=>null,
		'selector_prefix'=>null,
		);
		
		public function __construct(string $style=null){
			if(isset($style)){
				$this->load($style);	
			}
		}
		/* Config */
		public function __set($n,$v){
			if(array_key_exists($n,$this->config)){
				$this->config[$n] = $v;	
			}
			return $this;
		}
		/* Load style from String */
		public function load(string $style){
			$this->cssData = $this->parse($style);
		}
		/* Load style from file */
		public function loadFile(string $cssfile){
			if(!file_exists($cssfile))throw new Exception('Supplied css file does not exist');
			$this->load(file_get_contents($cssfile));
		}
		/* 	Parse Style declarations
			Currently Supports
			@ import url
			@ Media query
			@ Keyframes 
		@ font-face */
		private function parse(string $style){
			$css = array('rules'=>[],'ruleset'=>[],'import'=>[]);
			$g = str_replace(array("\r","\n"),'',$style);
			$g = preg_replace('#/\*((?!\*/).|\n)+\*/#','',$g);
			$g = preg_replace('/\s+\s/',' ',$g);
			if(preg_match_all('/@media([^{]+)\{([\s\S]+?})\s*}/',$g,$m)){
				$g = preg_replace('/@media[^{]+\{([\s\S]+?})\s*}/','',$g);
				$r = [];
				foreach($m[1] as $i=>$j){
					$n = trim($j,' ');
					$v = trim($m[2][$i],' ');
					$r[] = array('s'=>$n,'v'=>$this->matchRules($v));
				}
				$css['ruleset']['media'] = $r;
			}
			
			if(preg_match_all('/@font-face[^{]+\{([\s\S]+?)\}/',$g,$m)){
				$g = preg_replace('/@font-face[^{]+\{([\s\S]+?})/','',$g);
				
				$r = array_map(function($e){
					return 'font-face{'.$e.'}';
				},$m[1]);
				$r = implode('',$r);
				$css['import']['fontFace'] = $this->matchRules($r);
				
			}
			if(preg_match_all("#@import+\surl([^;].+?);#",$g,$m)){
				$g = preg_replace('#@import+\surl+[^\;](.*?);#','',$g);
				$r = array();
				foreach($m[1] as $i=>$j){
					$j = trim($j,'()');
					$j = preg_replace('/[\"\']/','',$j);
					$r[] = $j;
				}
				$css['import']['URL'] = $r;
			}
			if(preg_match_all('/@([^{]+)\{([\s\S]+?})\s*}/',$g,$m)){
				$g = preg_replace('/@([^{]+)\{([\s\S]+?})\s*}/','',$g);
				$r = array();
				foreach($m[1] as $i=>$j){
					if(stripos($j,'keyframes') ===false)continue;
					$n = trim($j,' ');
					$n = explode(',',$n);
					$e = $this->matchRules($m[2][$i]);
					$r[] = array('s'=>$n,'v'=>$e);
				}
				$css['ruleset']['keyframes'] = $r;
			}
			
			
			$css['rules'] = $this->matchRules($g);
			
			return $css;
		}
		/* Search for selector declaration 
			@ This method can only search normal rule block	
		*/
		public function findSelector($search){
			if(is_array($search))$search = implode(',',$search);
			$data = $this->_SearchSelector($search,'blocks');
			
			$t = count($data);
			for($i=0;$t > $i;$i++){
				unset($data[$i]['i']);
				unset($data[$i]['@']);
			}
			return $data;
		}
		/* Search for selector declaration 
			Return list of class selectors
			@ This method can only search normal rule block	
		*/
		public function findSelectorClassList(){
			$t = count($this->cssData['rules']);
			$r = array();
			for($i=0;$t > $i;$i++){
				$sel = [];
				foreach($this->cssData['rules'][$i]['s'] as $k=>$e){
					if(preg_match('/\.{1}([\w\-\_]+)/',$e) and !in_array($e,$r)){
						$r[] = $e;
					}
				}
			}
			return $r;
		}
		/* Search for selector declaration 
			Return list of ID selectors
			@ This method can only search normal rule block	
		*/
		public function findSelectorIDs(){
			$t = count($this->cssData['rules']);
			$r = array();
			for($i=0;$t > $i;$i++){
				$sel = [];
				foreach($this->cssData['rules'][$i]['s'] as $k=>$e){
					if(preg_match('/\#([\w\-\_]+)/',$e) and !in_array($e,$r)){
						$r[] = $e;
					}
				}
			}
			return $r;
		}
		
		public function matchSelector(string $search){
			$search = strtolower($search);
			$search = explode(',',$search);
			$List  = [];
			foreach($search as $i=>$j){
				if($j==':header'){
					unset($search[$i]);
					$search = array_merge($search,array('h1','h2','h3','h4','h5','h6'));	
					}else if($j==':submit'){
					$search[] = 'button[type=submit]';
					$search[] = 'input[type=submit]';
					unset($search[$i]);
					}else if(preg_match('/\[(class|id)(\*\=|\=|\^\=|\$\=|\~\=){0,2}(.*?)\]/',$j,$m)){
					
					if($m[1]=='class'){
						$List[] = $m;
						}elseif($m[1]=='id'){
						$List[] = $m;
					}
					unset($search[$i]);
				}
			}
			
			if(!empty($List)){
				$t = count($this->cssData['rules']);
				for($i=0;$t > $i;$i++){
					$sel = [];
					$s = implode(',',$this->cssData['rules'][$i]['s']);
					preg_match_all('/\.{1}([\w\-\_]+)/',$s,$m);
					preg_match_all('/\#{1}([\w\-\_]+)/',$s,$n);
					foreach($List as $a=>$b){
						$attr = false;
						if($b[1]=='class' and !empty($m[1])){
						$attr = $m[0];
						}else if($b[1]=='id' and !empty($n[1])){
						$attr = $n[0];
						}
						
						if(!$attr)continue;
						if($b[2] !='=' and $b[2] !=''){
						$c = array_filter($attr,function($e)use($b){
							$e = ltrim($e,'#.');
							$pos = stripos($e,$b[3]);
							$len = strlen($e);
							if($pos ===false)return false;
							
							if($b[2]=='*=' and $pos !==false)return true;
							if($b[2]=='^=' and $pos ===0)return true;
							if($b[2]=='~=' and $pos !==false)return true;
							if($b[2]=='$=' and $pos !==false and substr($e,-strlen($b[3]))===$b[3]){
							
							return true;
							}
							return false;
						});
						if(!empty($c))$sel = array_merge($sel,$c);
						}else if($b[2]=='=' and in_array($b[3],$attr)){
						$sel[] = $b[3];
						}else if($b[2]==''){
							$sel = $attr;
						}
						
					}
					if(empty($sel))continue;
					$search = array_merge($search,$sel);
					
				}
			}
			$search = array_unique($search);
			return $search;
		}
		/* Search for selector declaration 
			Return list of tag selectors
			@ This method can only search normal rule block	
		*/
		public function findSelectorTags(){
			$t = count($this->cssData['rules']);
			$r = array();
			for($i=0;$t > $i;$i++){
				$sel = [];
				foreach($this->cssData['rules'][$i]['s'] as $k=>$e){
					
					if(preg_match_all('/^[\w\_\-]+/i',$e,$m) and !in_array($e,$r)){
						$r[] = $e;
					}
					}
				}
				
				return $r;
		}
		
		
		private function _SearchSelector($search,$media=null){
			$r = array();
			$t = count($this->cssData['ruleset']['media']);
			if($media==null || $media=='media'){
				$search_r = explode(',',$search);
				for($i=0;$t > $i;$i++){
					foreach($this->cssData['ruleset']['media'][$i]['v'] as $k=>$j){
						$sel = [];
						foreach($j['s'] as $l=>$m){
							$s = preg_split('/([\>\+\s\~])/',$m,null);
							if(preg_match_all('/(.*)(?:(\[(.*)\]))/',$m,$n)){
								$s = array_merge($s,$n[1]);
								if(!empty($n[2])){
									$s = array_merge($s,$n[2]);
								}
							}
							if(preg_match_all('/[\:\#\.]{1}([\w\-_]+)\b/',$m,$n)){
								if(!empty($n[1])){
									$s = array_merge($s,$n[0]);
								}
							}
							if(!in_array($m,$s)){
								$s[] = $m;
							}
							$diff = array_intersect($s,$search_r);
							if(!empty($diff)){
								$sel[] = $m;
							}
						}
						if(!empty($sel)){
							$e = array();
							$e['selector'] = implode(',',$j['s']);
							$e['@'] = 'media';
							$e['properties'] = $j['v'];
							$e['search'] = $sel;
							$e['media'] = $this->cssData['ruleset']['media'][$i]['s'];
							$e['i'] = $k;
							$r[] = $e;
						}
						
					}
				}
			}
			if($media==null || $media=='keyframes'){
				$t = count($this->cssData['ruleset']['keyframes']);
				for($i=0;$t > $i;$i++){
					$n = ($this->cssData['ruleset']['keyframes'][$i]['s'][0]);
					$n = substr($n,strrpos($n,' ')+1);
					if(stripos($n,$search) !==false){
						$e = array();
						$e['selector'] = $this->cssData['ruleset']['keyframes'][$i]['s'][0];
						$e['@'] = 'keyframes';
						$e['properties'] = $this->cssData['ruleset']['keyframes'][$i]['v'];
						$e['search'] = $n;
						$e['i'] = $i;
						$r[] = $e;
					}
				}
			}
			if($media==null || $media=='blocks'){
				$t = count($this->cssData['rules']);
				$search_r = explode(',',$search);
				
				for($i=0;$t > $i;$i++){
					$sel = [];
					foreach($this->cssData['rules'][$i]['s'] as $k=>$e){
						$s = preg_split('/([\>\+\s\~])/',$e,null);
						if(preg_match_all('/(.*)(?:(\[(.*)\]))/',$e,$m)){
							
							$s = array_merge($s,$m[1]);
							if(!empty($m[2])){
								$s = array_merge($s,$m[2]);
							}
						}
						if(preg_match_all('/[\:\#\.]{1}([\w\-_]+)\b/',$e,$m)){
							if(!empty($m[1])){
								$s = array_merge($s,$m[0]);
							}
							
						}
						
						if(!in_array($e,$s)){
							$s[] = $e;
						}
						
						$diff = array_intersect($s,$search_r);
						
						if(!empty($diff)){
							$sel[] = $e;
						}
					}
					if(!empty($sel)){
						$j = $this->cssData['rules'][$i];
						$e = array();
						$e['selector'] = implode(',',$j['s']);
						$e['@'] = 'block';
						$e['properties'] = $j['v'];
						$e['search'] = $sel;
						$e['i'] = $i;
						$r[] = $e;
					}
					
				}
			}
			if($media==null || $media=='fontface'){
				$t = count($this->cssData['import']['fontFace']);
				for($i=0;$t > $i;$i++){
					if(array_key_exists('family',$this->cssData['import']['fontFace'][$i]['v']) and stripos($this->cssData['import']['fontFace'][$i]['v']['family'],$search) !==false){
						$e = array();
						$e['@'] = 'fontface';
						$e['selector'] = 'fontFace';
						$e['properties'] = $this->cssData['import']['fontFace'][$i]['v'];
						$e['search'] = $this->cssData['import']['fontFace'][$i]['v']['family'];
						$e['i'] = $i;
						$r[] = $e;
					}
				}
			}
			if($media==null || $media=='url'){
				$t = count($this->cssData['import']['URL']);
				for($i=0;$t > $i;$i++){
					$src = $this->cssData['import']['URL'][$i];
					if(stripos($src,$search) !==false){
						$e = array();
						$e['@'] = 'url';
						$e['selector'] = 'URL';
						$e['properties'] = $src;
						$e['search'] = $search;
						$e['i'] = $i;
						$r[] = $e;
					}
				}
			}
			
			return $r;
		}
		
		private function matchRules(string $source){
			preg_match_all('/(.+?)\{\s?(.+?)\s?\}/', $source, $m);
			$r = array();
			foreach($m[1] as $i=>$j){
				if(preg_match('/\{|\}|@/',$j)){
					continue;	
				}
				$j = trim($j);$v = preg_replace('/\s+\s/',' ',$m[2][$i]);
				$j = explode(',',$j);
				$j = array_map('trim',$j);
				if(strpos($v,';')){
					preg_match_all('/([\w\-\_]+)\:([^\;].*?);/i',$v,$st);
					$v = [];
					foreach($st[1] as $k=>$l){
						$v[$l] = trim($st[2][$k]);
					}
					}else{
					$st = explode(':',$v,2);
					$v = [];
					$k = $st[0];
					$v[$k] = $st[1];
					
				}
				$r[] = array('s'=>$j,'v'=>$v);
			}
			return $r;
		}
		/* Search and remove selector declaration 
			@ This method can only search normal rule block	
			@ Example 
			@ input{
			@
			@	}
		*/
		public function removeSelector($search=null){
			if(!isset($search)){
				$this->cssData = array();
				return $this;
			}
			if(is_array($search))$search = implode(',',$search);
			$data = $this->_SearchSelector($search,'blocks');
			
			$t = count($data);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				if($data[$i]['@']=='block' and array_key_exists($data[$i]['i'],$this->cssData['rules'])){
					
					foreach($data[$i]['search'] as $a=>$b){
						$n = array_search($b,$this->cssData['rules'][$k]['s']);
						if($n !==false){
							unset($this->cssData['rules'][$k]['s'][$n]);
						}
					}
					if(empty($this->cssData['rules'][$k]['s'])){
						unset($this->cssData['rules'][$k]);
					}
					unset($data[$i]['i']);
					unset($data[$i]['@']);
					unset($data[$i]['search']);
				}
				
			}
			
			return $data;
		}
		/* Search and append to selector declaration 
			@ This method can only search normal rule block	
			@ Example:
			@	input,button{
			@
			@	}
		*/
		public function appendSelector($search,string $selector=null,array $prop=null){
			if(is_array($search))$search = implode(',',$search);
			$data = $this->_SearchSelector($search,'blocks');
			$t = count($data);
			$do = isset($prop);
			if(!is_null($selector))$selector = trim($selector,' ');
			
			$do2 = (isset($selector) and !empty($selector));
			for($i=0;$t > $i;$i++){
				if($data[$i]['@']=='block' and array_key_exists($data[$i]['i'],$this->cssData['rules'])){
					$k = $data[$i]['i'];
					
					if($do){
						foreach($prop as $a=>$b){
							if(is_null($b)){
								unset($this->cssData['rules'][$k]['v'][$a]);	
								}else{
								$this->cssData['rules'][$k]['v'][$a] = $b;
							}
						}
						
					}
					if($do2){
						$this->cssData['rules'][$k]['s'][] = $selector;
					}
					if(empty($this->cssData['rules'][$k]['v'])){
						unset($this->cssData['rules'][$k]);
					}
				}
				
			}
			
			return $this;
		}
		/* Add new rule declaration
			@ This method can only perform on normal rule block	
		*/
		public function addSelector(string $selector,array $prop){
			$selector = explode(',',$selector);
			$selector = array_map('trim',$selector);
			$prop = array_filter($prop,function($a,$b){
				if(is_null($a) || !is_scalar($a))return false;
				return true;
			},ARRAY_FILTER_USE_BOTH);
			
			$this->cssData['rules'][] = array('s'=>$selector,'v'=>$prop);
			return $this;
		}
		/* Search for style declaration under media query block */
		public function findMediaSelector($search){
			if(is_array($search))$search = implode(',',$search);
			$data = $this->_SearchSelector($search,'media');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				unset($data[$i]['i']);
				unset($data[$i]['@']);
				unset($data[$i]['media']);
			}
			return $data;
		}
		/* Add new rule declaration under media query block */
		public function addMediaSelector(string $media,string $selector,array $prop){
			$selector = explode(',',$selector);
			$selector = array_map('trim',$selector);
			$prop = array_filter($prop,function($a,$b){
				if(is_null($a) || !is_scalar($a))return false;
				return true;
			},ARRAY_FILTER_USE_BOTH);
			$data = $this->_matchMedia($media);
			
			$t = count($data);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				$e = array('s'=>$selector,'v'=>$prop);
				$this->cssData['ruleset']['media'][$k]['v'][] = $e;
				
			}
			
			return $this;
		}
		/* Declare new media query block */
		public function addMedia(string $media){
			$this->cssData['ruleset']['media'][] = array('s'=>$media,'v'=>[]);
			return $this;
		}
		/* Append to selector declaration under media query block */
		public function appendMediaSelector(string $media,string $search,string $selector=null,array $prop=null){
			$data = $this->_matchMedia($media);
			
			$t = count($data);
			$search_r = explode(',',$search);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				foreach($data[$i]['rules'] as $a=>$b){
					$e = $b['selector'];
					$ka = $b['i'];
					$s = preg_split('/([\>\+\s\~])/',$e,null);
					if(preg_match_all('/(.*)(?:(\[(.*)\]))/',$e,$m)){
						$s = array_merge($s,$m[1]);
						if(!empty($m[2])){
							$s = array_merge($s,$m[2]);
						}
					}
					if(preg_match_all('/[\:\#\.]{1}([\w\-_]+)\b/',$e,$m)){
						if(!empty($m[1])){
							$s = array_merge($s,$m[0]);
						}
					}
					$m = explode(',',$e);
					$s = array_merge($s,$m);
					$s = array_filter($s);
					$diff = array_intersect($s,$search_r);
					
					if(empty($diff))continue;
					if(isset($selector)){
						array_push($this->cssData['ruleset']['media'][$k]['v'][$ka]['s'],$selector);
					}
					if(isset($prop) and !empty($prop)){
						foreach($prop as $kc=>$kd){
							if(is_null($kd)){
								unset($this->cssData['ruleset']['media'][$k]['v'][$ka]['v'][$kc]);
								}else{
								$this->cssData['ruleset']['media'][$k]['v'][$ka]['v'][$kc] = $kd;
							}
						}
						if(empty($this->cssData['ruleset']['media'][$k]['v'][$ka]['v'])){
							unset($this->cssData['ruleset']['media'][$k]['v'][$ka]);	
						}
						if(empty($this->cssData['ruleset']['media'][$k]['v'])){
							unset($this->cssData['ruleset']['media'][$k]);	
						}
						
					}
				}
			}
			return $this;
		}
		/* Remove rule declaration under media query block */
		public function removeMediaSelector(string $media=null,$selector){
			$data = $this->_matchMedia($media);
			$t = count($data);
			if(is_array($selector))$selector = implode(',',$selector);
			$search_r = explode(',',$selector);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				if($search=='*'){
					unset($this->cssData['ruleset']['media'][$k]);	
					continue;	
				}
				foreach($data[$i]['rules'] as $a=>$b){
					$e = $b['selector'];
					$ka = $b['i'];
					$s = preg_split('/([\>\+\s\~])/',$e,null);
					if(preg_match_all('/(.*)(?:(\[(.*)\]))/',$e,$m)){
						$s = array_merge($s,$m[1]);
						if(!empty($m[2])){
							$s = array_merge($s,$m[2]);
						}
					}
					if(preg_match_all('/[\:\#\.]{1}([\w\-_]+)\b/',$e,$m)){
						if(!empty($m[1])){
							$s = array_merge($s,$m[0]);
						}
					}
					$m = explode(',',$e);
					$s = array_merge($s,$m);
					$s = array_filter($s);
					$diff = array_intersect($s,$search_r);
					if(!empty($diff)){
						unset($this->cssData['ruleset']['media'][$k]['v'][$ka]);
					}
				}
				if(empty($this->cssData['ruleset']['media'][$k]['v'])){
					unset($this->cssData['ruleset']['media'][$k]);	
				}
			}
			$this->cssData['ruleset']['media'] = array_values($this->cssData['ruleset']['media']);
			return $this;
		}
		/* Remove media query declaration block */
		public function removeMedia($search){
			$data = $this->_matchMedia($search);
			$t = count($data);
			
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				if(array_key_exists($k,$this->cssData['ruleset']['media'])){
					unset($this->cssData['ruleset']['media'][$k]);	
				}
			}
			
			$this->cssData['ruleset']['media'] = array_values($this->cssData['ruleset']['media']);
			return $this;
		}
		/* Search and return media query block that matches
		specified conditions */
		public function matchMedia($search){
			return $this->_matchMedia($search);
		}
		
		private function _matchMedia(string $search=null){
			$result = [];
			$search = preg_replace('/\s+\s/',' ',$search);
			$search = explode(',',$search);
			
			$searchP = [];
			foreach($search as $i=>$j){
				$j = trim($j,' ');
				if($j==='')continue;
				if(preg_match('/([\w_-]+?)\s{0,1}(>|<|=|>=|<>|<=|\:)\s{0,1}([0-9\.\w\-_]+)/',$j,$m)){
					$k = $m[1];
					
					if($m[2]===':')$m[2] = '==';
					$searchP[$k] = array('anp'=>$m[2],'value'=>$m[3]);
					}else{
					$searchP[$j] = array('anp'=>'==','value'=>true);
				}
			}
			
			$t = count($this->cssData['ruleset']['media']);
			for($i=0; $t > $i;$i++){
				$s = ($this->cssData['ruleset']['media'][$i]['s']);
				if(preg_match_all('/\(([^\)].*?)\)/',$s,$m)){
					$q = [];
					foreach($m[1] as $k=>$j){
						$j = explode(':',$j,2);
						if(is_null($j[1])){
							$j[1] = true;
							}else{
							$j[1] = preg_replace('/[^\d\.]/','',$j[1]);
							$j[1] = (float)$j[1];
						}
						$k = $j[0];
						$q[$k] = $j[1];
					}
					$sel = false;
					foreach($q as $k=>$m){
						if(!array_key_exists($k,$searchP))continue;
						$j = $searchP[$k];
						$arg = '$ans = ('.$m.' '.$j['anp'].' \''.$j['value'].'\');';
						
						eval($arg);
						if($ans===true){
							$sel = true;
							}else{
							$sel = false;
						}
					}
					if($sel || empty($searchP)){
						$e = array();
						$e['media'] = $this->cssData['ruleset']['media'][$i]['s'];
						$e['i'] = $i;
						$r = [];
						foreach($this->cssData['ruleset']['media'][$i]['v'] as $a=>$b){
							$r[] = array('selector'=>implode(',',$b['s']),'properties'=>$b['v'],'i'=>$a,'@'=>'block');
						}
						$e['rules'] = $r;
						$result[] = $e;
					}
				}
			}
			
			return $result;
		}
		/* Search and return font-face rule */
		public function findFont($search){
			$data = $this->_SearchSelector($search,'fontface');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				unset($data[$i]['i']);
				unset($data[$i]['@']);
				unset($data[$i]['search']);
			}
			return $data;
		}
		/* Search and Remove font-face rule */
		public function removeFont(string $search=null){
			if(!isset($search)){
				$this->cssData['import']['fontFace'] = array();
				return $this;
			}
			$data = $this->_SearchSelector($search,'fontface');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				if(array_key_exists($k,$this->cssData['import']['fontFace'])){
					unset($this->cssData['import']['fontFace'][$k]);
				}
			}
			
			return $this;
		}
		/* Add new font-face rule block */
		public function addFont(string $name,string $url){
			$name = trim($name,' "\'');
			$prop = array('family'=>$name,'src'=>'url("'.$url.'")');
			$this->cssData['import']['fontFace'][] = array('s'=>['font-face'],'v'=>$prop);
			return $this;
		}
		
		/* Search and remove from import declaration block */
		public function removeImport(string $search=null){
			if(!isset($search)){
				$this->cssData['import']['URL'] = array();
				return $this;
			}
			$data = $this->_SearchSelector($search,'url');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				if($data[$i]['@']!=='url')continue;
				$k = $data[$i]['i'];
				if(array_key_exists($k,$this->cssData['import']['URL'])){
					unset($this->cssData['import']['URL'][$k]);
				}
			}
			
		}
		/* Add new @import rule block */
		public function addImport(string $url){
			
			$this->cssData['import']['URL'][] = $url;
			return $this;
		}
		/* Search and return @import rule block */
		public function findImport($search){
			$data = $this->_SearchSelector($search,'url');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				unset($data[$i]['i']);
				unset($data[$i]['@']);
				unset($data[$i]['search']);
			}
			return $data;
		}
		/* Search and return keyframes rule block */
		public function findKeyFrame($search){
			$data = $this->_SearchSelector($search,'keyframes');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				unset($data[$i]['i']);
				unset($data[$i]['@']);
				unset($data[$i]['search']);
			}
			
			return $data;
		}
		/* Search and Append new keyframe tween block */
		public function appendKeyframe(string $search,string $tween,array $prop){
			$data = $this->_SearchSelector($search,'keyframes');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				$this->cssData['ruleset']['keyframes'][$k]['v'][] = array('s'=>[$tween],'v'=>$prop);
				
			}
			return $this;
		}
		/* Add new keyframe block */
		public function addKeyframe(string $name){
			
			$name = 'keyframes '.$name;
			$this->cssData['ruleset']['keyframes'][] = array('s'=>[$name],'v'=>[]);
			return $this;
		}
		/* Search and remove from  keyframes declaration */
		public function removeKeyFrame(string $search,string $tween=null){
			$data = $this->_SearchSelector($search,'keyframes');
			$t = count($data);
			for($i=0;$t > $i;$i++){
				$k = $data[$i]['i'];
				$tt = count($this->cssData['ruleset']['keyframes'][$k]['v']);
				for($a=0;$tt > $a;$a++){
					$n = ($this->cssData['ruleset']['keyframes'][$k]['v'][$a]['s'][0]);
					if($tween == $n){
						unset($this->cssData['ruleset']['keyframes'][$k]['v'][$a]);
					}
				}
				if(empty($this->cssData['ruleset']['keyframes'][$k]['v'])){
					unset($this->cssData['ruleset']['keyframes'][$k]);
				}
			}
		}
		/* Compile and export CSS declaration blocks */
		public function export($output = null){
			
			$lines = [];
			$nbreak = "\n\r";
			if($this->config['minify']){
				$nbreak = '';
			}
			//Render Imports;
			foreach($this->cssData['import']['URL'] as $i=>$j){
				$lines[] = '@import url("'.$j.'");';
			}
			foreach($this->cssData['import']['fontFace'] as $i=>$j){
				$lines[] = '@font-face {'."\n".'font-family: '.$j['v']['family'].';'.$nbreak.'src: '.$j['v']['src'].';'.$nbreak.'}';
			}
			//Parse Keyframes
			
			foreach($this->cssData['ruleset']['keyframes'] as $i=>$j){
				$r = [];
				foreach($j['v'] as $a=>$b){
					$n = $b['s'][0];
					if(!isset($r[$n])){
						$r[$n] = $b['v'];
						}else{
						$r[$n] = array_merge($r[$n],$b['v']);
					}
				}
				
				$k = $j['s'][0];
				$g = $k.'{'.$nbreak;
				foreach($r as $a=>$b){
					$g .= $a.' {'.$nbreak;
					foreach($b as $c=>$d){
						$g .= $c.':'.$d.';'.$nbreak;
					}
					$g .= '}'.$nbreak;
				}
				$g .= '}';
				
				$lines[] = $g;
			}
			
			//Parse block rules
			$rules = array_values($this->cssData['rules']);
			$t = count($rules);
			$d = [];
			$db = [];
			for($i=0;$t > $i;$i++){
				if($this->config['selector_prefix']){
					foreach($rules[$i]['s'] as $a => $b){
						if(stripos($b,$this->config['selector_prefix'])===0)continue;
						$rules[$i]['s'][$a] = $this->config['selector_prefix'].$b;
					}
				}
				$s = implode(',',$rules[$i]['s']);
				
				$r = '';
				foreach($rules[$i]['v'] as $a=>$b){
					$r .= $a.':'.$b.';'.$nbreak;
				}
				if($r=='')continue;
				$r = $s.'{'.$nbreak.$r;
				if(isset($db[$s]) and $db[$s]== $r)continue;
				if($r=='')continue;
				$db[$s] = $r;
				$r .= '}';
				$d[]  = $r;
			}
			$d = (array_values($d));
			$d = implode($nbreak,$d);
			$lines[] = $d;
			if($this->config['remove_mediaquery'] ==true){
				//Parse media queries
				$t = 0;
				}else{
				$rules = array_values($this->cssData['ruleset']['media']);
				$t = count($rules);
			}
			
			
			for($i=0;$t > $i;$i++){
				$q = $rules[$i]['s'];
				$da = [];$db = [];
				$r = '';$da[$q] = true;
				foreach($rules[$i]['v'] as $a=>$b){
					foreach($b['s'] as $c => $d){
						if(stripos($d,$this->config['selector_prefix'])===0)continue;
						$b['s'][$c] = $this->config['selector_prefix'].$d;
					}
					$s = implode(',',$b['s']);
					
					
					$r .= $s.'{'.$nbreak;
					foreach($b['v'] as $k=>$v){
						$r .= $k.':'.$v.';'.$nbreak;
					}
					$r .= '}'.$nbreak;
					if(isset($db[$s]) and $db[$s]==$r)continue;
					$db[$s] = $r;
				}
				if($r !==''){
					$r = '@media '.$q.'{'.$nbreak.$r;	
					$r .= '  }';
					$lines[] = $r;
				}
				
			}
			$lines = implode($nbreak,$lines);
			if(isset($output)){
				file_put_contents($output,$lines);
				return true;
			}
			return $lines;
		}
	}
?>