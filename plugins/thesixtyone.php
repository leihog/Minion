<?php
namespace Bot\Plugin;
use Bot\Bot;

class Trac extends Plugin 
{	
	public function cmdT61( \Bot\Event\Irc $event, $profile = false )
	{
		$this->cmdThesixtyone($event, $profile);
	}
	
	public function cmdThesixtyone( \Bot\Event\Irc $event, $profile = false )
	{
		$nick = $event->getHostmask()->getNick();
		$source = $event->getSource();
		
		$content = file_get_contents("http://www.thesixtyone.com/{$profile}/");
		if ($content)
		{
			$dom = new \DOMDocument('1.0', 'utf-8');
			$dom->validateOnParse = true;
			@$dom->loadHTML($content);
			$xpath = new \DOMXPath($dom);

			$reputation = $this->xquery($xpath, '//table[contains(@class, "levelbar")]//div[contains(@class, "points")]');
			$level = trim(str_replace('level', '', $this->xquery($xpath, '//table[contains(@class, "levelbar")]//div[contains(@class, "label")]')));
			$plays = $this->xquery($xpath, '//div[@id="listener_numbers"]/span[contains(@class, "number")]');

            $this->doPrivmsg($source, "thesixtyone.com stats for {$profile}:");
		    $this->doPrivmsg($source, "Level {$level}, Reputation {$reputation}, Plays {$plays}");			
    
			$time = $this->xquery($xpath, '//div[@id="listener_last_played"]/div[@class="label"]');
			$song = $this->xquery($xpath, '//div[@id="listener_last_played"]/div[@class="song"]/a', 0);
			$artist = $this->xquery($xpath, '//div[@id="listener_last_played"]/div[@class="song"]/a', 1);			
			$url = $xpath->query('//div[@id="listener_last_played"]/div[@class="song"]/a')->item(0)->getAttribute('href');

			if (!empty($time) && !empty($song))
			{
			    $this->doPrivmsg($source, "$profile $time $song by $artist (http://thesixtyone.com{$url})");
			}
			return;
		}

		$this->doPrivmsg($source, "{$nick}: I couldn't find any status for $profile on thesixtyone.com.");
	}
	
    protected function xquery($xpath, $query, $index = 0)
    {
        $r = $xpath->query($query);
        if ($r->length > $index)
        {
            return trim($r->item($index)->nodeValue);
        }
        else
        {
            return '';
        }
    }
}
