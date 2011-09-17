<?

include('myorm.php');
include('imdbphp/imdb.class.php');
include("imdbphp/imdb_person.class.php");

MyORM::openDb('mysql:host=localhost;dbname=MYSQL_DATABASE', 'MYSQL_USER', 'MYSQL_PASSWORD');

MyORM::addAssociation('title', 'aka', '1:n', 'title.titleImdb = aka.titleImdb');
MyORM::addAssociation('title', 'credit', '1:n', 'title.titleImdb = credit.titleImdb');
MyORM::addAssociation('title', 'genre', '1:n', 'title.titleImdb = genre.titleImdb');
MyORM::addAssociation('title', 'tag', '1:n', 'title.titleImdb = tag.id');
MyORM::addAssociation('name', 'credit', '1:n', 'name.nameImdb = credit.nameImdb');
MyORM::addAssociation('login', 'tag', '1:n', 'login.username = tag.username');

class cputils {
	public static function saveThumbnail($imgPath, $thumbPath, $width, $height) {
		$path=dirname(__FILE__).'/';
		$c = '/usr/bin/convert -resize '.$width.'x'.$height.' '.$path.$imgPath.' '.$path.$thumbPath;
		exec($c);
		exec('/usr/bin/chmod 644 '.$path.$thumbPath);
	}
}

class aka extends MyORM {
}

class credit extends MyORM {
}

class genre extends MyORM {
	public function getHtmlCode() {
		return '<a class="genrebox" href="index.php?section=titlesbygenre&amp;genre='.$this->genre.'">'.$this->genre.'</a>';
	}
}

class login extends MyORM {
}

class name extends MyORM {
	public function updateFromImdb() {
		$imdb = new imdb_person($this->nameImdb);
		$this->personname = html_entity_decode($imdb->name());
		if (!file_exists($this->getImgPath())) $imdb->savephoto($this->getImgPath());
		if (!file_exists($this->getImgPath(true))) cputils::saveThumbnail($this->getImgPath(),$this->getImgPath(true),null,100);
		$this->save();
	}
	public function getImgPath($thumb=false) {
		return 'pic/nm'.$this->nameImdb.($thumb?'t':'').'.jpg';
	}
	public function getHtmlImgPath($thumb=false) {
		return file_exists($this->getImgPath($thumb))?$this->getImgPath($thumb):'BgBlack30.png';
	}
	public function getHtmlCode() {
		return '<div class="filmbox"><a href="'.$this->getImdbLink().'"><img src="'.$this->getHtmlImgPath(true).'" /></a><div><b>'.$this->personname.'</b><a href="'.$this->getWikipediaLink().'" class="wikipedia"></a><a href="'.$this->getImdbLink().'" class="imdb"></a></div></div>'."\n";
	}
	public function getImdbLink() {
		return 'http://www.imdb.com/name/nm'.$this->nameImdb.'/';
	}
	public function getWikipediaLink($lang='de') {
		return 'http://www.google.at/search?q='.$this->personname.'+'.$lang.'.wikipedia.org&btnI=';
	}
}

class tag extends MyORM {
}

class title extends MyORM {
	public function updateFromImdb($cascade = false) {
		$imdb = new imdb($this->titleImdb);
		$this->filmname = html_entity_decode($imdb->title());
		$this->year = $imdb->year();
		$this->rating = $imdb->rating();
		$this->runtime = $imdb->runtime();
		if (!file_exists($this->getImgPath())) $imdb->savephoto($this->getImgPath());
		if (!file_exists($this->getImgPath(true))) cputils::saveThumbnail($this->getImgPath(),$this->getImgPath(true),null,100);
		$this->save();
		foreach($imdb->genres() as $genre) {
			$g = new genre(array('titleImdb'=>$this->titleImdb, 'genre'=>$genre));
			$g->save();
		}
		foreach($imdb->alsoknow() as $aka) {
			if (preg_match('/de|en/',$aka['lang']) || preg_match('/USA|English|International/',$aka['country'].$aka['comment'])) {
				$a = new aka(array('titleImdb'=>$this->titleImdb, 'akaTitle'=>html_entity_decode($aka['title'])));
				$a->save();
			}
		}
		if ($cascade) {
			$credits=array('actor'=>$imdb->cast(),'director'=>$imdb->director(),'writer'=>$imdb->writing());
			foreach($credits as $id => $persons) {
				foreach($persons as $person) {
					$c = new name(array('nameImdb'=>$person['imdb']));
					$c->personname = $person['name'];
					$c->save();
					$f = new credit(array('titleImdb'=>$this->titleImdb, 'nameImdb'=>$person['imdb'], 'cat'=>$id));
					$f->role = html_entity_decode($person['role']);
					$f->save();
				}
			}
		}
	}
	public function getImgPath($thumb=false) {
		return 'pic/tt'.$this->titleImdb.($thumb?'t':'').'.jpg';
	}
	public function getHtmlImgPath($thumb=false) {
		return file_exists($this->getImgPath($thumb))?$this->getImgPath($thumb):'BgBlack30.png';
	}
	public function __toString() {
		return "<p><a href='http://www.imdb.com/title/tt$this->titleImdb'>$this->filmname</a> ($this->year, $this->rating, $this->runtime): <img src='".$this->getImgPath()."' /></p>";
	}
	public function getDirector() {
		$director = new credit();
		return $director->selectFirst('WHERE credit.cat=:cat and credit.titleImdb=:titleImdb',array(':cat'=>'director',':titleImdb'=>$this->titleImdb))->name;
	}
	public function getHtmlCode() {
		return '<div class="filmbox"><a href="index.php?section=titledetail&amp;id='.$this->titleImdb.'"><img src="'.$this->getHtmlImgPath(true).'" /></a><div><b>'.$this->filmname.'</b> ['.$this->year.']<br />'.$this->getDirector()->personname.'<a href="'.$this->getWikipediaLink().'" class="wikipedia"></a><a href="'.$this->getImdbLink().'" class="imdb"></a></div></div>'."\n";
	}
	public function getDetailHtmlCode() {
		$box1 = '<div></div>';
	}
	public function getImdbLink() {
		return 'http://www.imdb.com/title/tt'.$this->titleImdb.'/';
	}
	public function getWikipediaLink($lang='de') {
		return 'http://www.google.at/search?q='.$this->filmname.'+'.$this->year.'+'.$lang.'.wikipedia.org&btnI=';
	}
	public function getJSONArray($detailed = false) {
		$genres = $akas = array();
		$a = array('id'=>$this->titleImdb, 'name'=>$this->filmname, 'year'=>$this->year, 'rating'=>$this->rating, 'runtime'=>$this->runtime, 'img'=>file_exists($this->getImgPath())?1:0);
		foreach($this->genres as $genre) $a['genre'][] = $genre->genre;
		foreach($this->akas as $aka) $a['aka'][] = $aka->akaTitle;
		if(!$detailed) $a['director'] = $this->getDirector()->personname;
		if($detailed) foreach($this->credits as $credit) $a[$credit->cat][$credit->nameImdb] = array($credit->role, $credit->name->personname);
		return $a;
	}
}

class controller {
	private $selectLimit;
	public function __construct($selectLimit=9999) {
		$this->selectLimit=$selectLimit;
	}
	public function listTitles() {
		$title = new title();
		foreach($title->select('ORDER BY filmname LIMIT '.$this->selectLimit) as $film)
			echo $film->getHtmlCode();
	}
	public function listGenres() {
		$genre = new genre();
		foreach ($genre->select('GROUP BY genre') as $g)
			echo $g->getHtmlCode();
	}
	public function listCredit($cat) {
		$credit = new credit();
		$req = $credit->select('WHERE credit.cat=:cat GROUP BY nameImdb ORDER BY count(*) desc LIMIT '.$this->selectLimit,array(':cat'=>$cat));
		foreach($req as $r)
			echo $r->name->getHtmlCode();
	}
	public function filterTitleByGenre($genre) {
		$t = new title();
		$titles = $t->select(',genre WHERE genre.titleImdb=title.titleImdb and genre.genre=:genre ORDER BY title.filmname',array(':genre'=>$genre));
		foreach($titles as $title)
			echo $title->getHtmlCode();
	}
	public function getTitleDetail($id) {
		$t = new title(array('titleImdb'=>$id));
		$t->updateFromImdb(true);
		$t->save();
		echo $t;
	}
}

?>
