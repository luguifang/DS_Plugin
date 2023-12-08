<?php
/*********************************************************************\
| (c)2017 codemonster                                                 |
|---------------------------------------------------------------------|
| This program is free software; you can redistribute it and/or       |
| modify it under the terms of the GNU General Public License         |
| as published by the Free Software Foundation; either version 2      |
| of the License, or (at your option) any later version.              |
|                                                                     |
| This program is distributed in the hope that it will be useful,     |
| but WITHOUT ANY WARRANTY; without even the implied warranty of      |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the       |
| GNU General Public License for more details.                        |
|                                                                     |
| You should have received a copy of the GNU General Public License   |
| along with this program; If not, see <http://www.gnu.org/licenses/> |
\*********************************************************************/
?>
<?php
class CodemonsterDlmSearchBt4g {
		private $url_domain = 'https://bt4gprx.com';
		public $preparedUrl = '';
		private $debug = true;
		private $tempFile = '/tmp/_DLM_bt4g.log';
		private $apiSearchMovies = '/search?q=%s&orderby=seeders&p=1&page=rss';
       
        private function DebugLog($str) 
		{
			if ($this->debug==true) 
			{
				file_put_contents($this->tempFile,$str."\r\n\r\n",FILE_APPEND);
			}
        }

        public function __construct() 
		{
			if(file_exists($this->tempFile))
			{
				unlink($this->tempFile);
			}

			if ($this->debug==true) 
			{
				ini_set('display_errors', 0);
				ini_set('log_errors', 1);
				ini_set('error_log', $this->tempFile);
			}
		}

        public function prepare($curl, $query) 
		{
			$this->preparedUrl = $this->url_domain.sprintf($this->apiSearchMovies, urlencode($query));
			$this->DebugLog($this->preparedUrl);
			$this->configureCurl($curl, $this->preparedUrl);                
        }

		public function parse($plugin, $response) 
		{
			$this->processResultList($plugin, $response);
        }
		
		/* Begin private methods */
		
		private function configureCurl($curl, $url)
		{
			$headers = array
				(
					'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*;q=0.8',
					'Accept-Language: en-us;q=0.7,en;q=0.3',
					'Accept-Encoding: deflate',
					'Accept-Charset: windows-1251,utf-8;q=0.7,*;q=0.7'
				);
				
				curl_setopt($curl, CURLOPT_HTTPHEADER,$headers); 
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_FAILONERROR, 1);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
				curl_setopt($curl, CURLOPT_TIMEOUT, 120);
				curl_setopt($curl, CURLOPT_USERAGENT, DOWNLOAD_STATION_USER_AGENT);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); 
		}
		
		public function executeCurl($url)
		{
			$curl = curl_init();
			$this->configureCurl($curl, $url);
			$content = curl_exec($curl);
			curl_close($curl);
			//$this->DebugLog($content);
			return $content;
		}
		
		private function processResultList($plugin, $response)
		{
			//$this->DebugLog($response);
			$xmlData = simplexml_load_string($response);
			//$this->DebugLog($xmlData);
			if(!(isset($xmlData) && isset($xmlData->channel) && isset($xmlData->channel->item)))
				return;

			foreach ($xmlData->channel->item as $movie)
			{
				//$this->DebugLog($movie->title);
				if(!(isset($movie) && isset($movie->link)))
					return;
				
				$recordToAdd = array();
				$recordToAdd["page"] = $movie->guid; //1. url	
				$recordToAdd["seeds"] = 9999; //2. seeds
				$recordToAdd["leechs"] = 9999; //3. leechs
				$recordToAdd["datetime"] = $movie->pubDate; //4. date
				$recordToAdd["title"] = $movie->title; //5. title
				$recordToAdd["download"] = $movie->link;
				
				$strDescription = str_replace(array('<![CDATA[', ']]>'),'',$movie->description);
				$lineList = explode('<br>',$strDescription);

				$recordToAdd["category"] = $lineList[2]; //7. category
				$recordToAdd["size"] = intval($this->changeDateUnit($lineList[1])); //8. size
				$recordToAdd["hash"] = $lineList[3]; //9. hash
				$this->addItemToPlugin($plugin, $recordToAdd);
			}
		}

		private function changeDateUnit($strData)
		{
			if (strpos($strData,"GB"))
			{
				preg_match('/\d+(?:\.\d+)?/', $strData, $matches);
				return floatval($matches[0]) * 1024 * 1024 * 1024;
			}
			if (strpos($strData,"MB"))
			{
				preg_match('/\d+(?:\.\d+)?/', $strData, $matches);
				return floatval($matches[0]) * 1024 * 1024;
			}
			if (strpos($strData,"KB"))
			{
				preg_match('/\d+(?:\.\d+)?/', $strData, $matches);
				return floatval($matches[0]) * 1024;
			}

			preg_match('/\d+(?:\.\d+)?/', $strData, $matches);
			return floatval($matches[0]);

		}
		
		private function addItemToPlugin($plugin, $recordToAdd)
		{
			if (array_key_exists('title', $recordToAdd) && strlen($recordToAdd["title"]) > 0) 
			{
				$plugin->addResult($recordToAdd["title"],
					$recordToAdd["download"],
					(float)$recordToAdd["size"],
					date('Y-m-d',strtotime(str_replace("'", "", $recordToAdd["datetime"]))),
					$recordToAdd["page"],
					$recordToAdd["hash"],
					(int)$recordToAdd["seeds"],
					(int)$recordToAdd["leechs"],
					$recordToAdd["category"]);
			}
		}
		
		/* End private methods */
}
?>