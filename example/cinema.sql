CREATE TABLE `aka` (
  `titleImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL default '',
  `akaTitle` varchar(100) character set utf8 collate utf8_bin NOT NULL default '',
  PRIMARY KEY  (`titleImdb`,`akaTitle`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `credit` (
  `titleImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL,
  `nameImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL,
  `cat` enum('director','actor','writer') character set utf8 collate utf8_bin NOT NULL,
  `role` varchar(40) character set utf8 collate utf8_bin NOT NULL,
  PRIMARY KEY  (`titleImdb`,`nameImdb`,`cat`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `genre` (
  `titleImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL,
  `genre` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  PRIMARY KEY  (`titleImdb`,`genre`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `login` (
  `username` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  `password` varchar(40) character set utf8 collate utf8_bin NOT NULL,
  `name` varchar(40) character set utf8 collate utf8_bin NOT NULL,
  PRIMARY KEY  (`username`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `name` (
  `nameImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL,
  `personname` varchar(100) character set utf8 collate utf8_bin default NULL,
  PRIMARY KEY  (`nameImdb`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `tag` (
  `id` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  `username` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  `type` enum('title','media') NOT NULL,
  `tag` varchar(20) character set utf8 collate utf8_bin NOT NULL,
  `stamp` date default NULL,
  PRIMARY KEY  (`id`,`username`,`tag`)
) ENGINE=MyISAM DEFAULT;

CREATE TABLE `title` (
  `titleImdb` varchar(10) character set utf8 collate utf8_bin NOT NULL,
  `filmname` varchar(100) character set utf8 collate utf8_bin default NULL,
  `year` int(4) default NULL,
  `rating` decimal(2,1) default NULL,
  `runtime` int(4) default NULL,
  `language` varchar(2) character set utf8 collate utf8_bin default NULL,
  PRIMARY KEY  (`titleImdb`)
) ENGINE=MyISAM DEFAULT;
