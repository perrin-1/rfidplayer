<?php
class Db {

	public $db;

	public function __construct($db){
		$this->db = new SQLite3($db);
		$this->init();
	}

	private function init(){
		//$this->dropTagTable();
		$this->createTagTable();
    $this->createTagStatsTable();
	}


	public function createTagTable(){
		return $this->db->exec('CREATE TABLE IF NOT EXISTS tagconfig (tag_id VARCHAR(255), tag_uri VARCHAR(255), tag_desc VARCHAR(255))');
	}
	
  public function createTagStatsTable(){
		return $this->db->exec('CREATE TABLE IF NOT EXISTS tagstats (tag_id TEXT, timestamp DATETIME DEFAULT CURRENT_TIMESTAMP )');
	}

	public function dropTagTable(){
		return $this->db->exec('DROP TABLE tagconfig');
	}

	public function insert($tag_id, $tag_uri, $tag_desc){
		return $this->db->exec("INSERT INTO tagconfig (tag_id, tag_uri, tag_desc) VALUES ('$tag_id', '$tag_uri', '$tag_desc')");
	}

	public function update($id, $tag_id, $tag_uri, $tag_desc){
		return $this->db->query("UPDATE tagconfig set tag_id='$tag_id', tag_uri='$tag_uri', tag_desc='$tag_desc' WHERE rowid=$id");
	}

	public function delete($id){
		return $this->db->query("DELETE FROM tagconfig WHERE rowid=$id");  
	}

	public function getAll(){
		return $this->db->query("SELECT rowid, * FROM tagconfig");
	}
  
  public function getLastReadTag(){
		return $this->db->query("SELECT ts.tag_id,ts.timestamp, tag_desc, tag_uri FROM tagstats ts  left join tagconfig on ts.tag_id = tagconfig.tag_id ORDER BY ts.timestamp DESC LIMIT 1");
	}
  
  public function getTagStats(){
		return $this->db->query("SELECT ts.tag_id,ts.timestamp, tag_desc FROM tagstats ts left join tagconfig on ts.tag_id = tagconfig.tag_id ORDER BY ts.timestamp DESC");
	}
   
	public function getById($id){
		return $this->db->query("SELECT rowid, * FROM tagconfig WHERE rowid=$id");
	}
  
}

?>