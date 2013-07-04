<?php
namespace Bot\Plugin;

use Bot\Bot as Bot;

/**
 * @todo keep track of users so that a user can't increase their own karma.
 */
class Karma extends Plugin
{
	protected $incResponses = array(
		"gained a level!", "is on the rise!", "leveled up!"
	);
	protected $decResponses = array(
		"took a hit! Ouch.", "took a dive.", "lost a life.", "lost a level."
	);

	protected $score;
	protected $max = 6;
	protected $limit = 3; // ($max / 2)

	public function init()
	{
		$terms = Bot::memory()->get('karma.terms');
		if (!$terms) {
			$this->score = array();
			return;
		}

		arsort($terms);
		if (count($terms) > $this->max) {
			$this->score = array_merge(
				array_slice($terms, 0, $this->limit, true),
				array_slice($terms, -$this->limit, $this->limit, true)
			);
		} else {
			$this->score = $terms;
		}
	}

	public function onPrivmsg(\Bot\Event\Irc $event)
	{
		$message = $event->getParam(1);
		$pattern = '/^\s*(?P<term>\S+[^+-])(?P<action>\+\+|--)$/i';

		if (preg_match($pattern, $message, $match)) {
			$action = $match['action'];
			$term = $this->normalizeTerm($match['term']);

			if ($this->termMatchesUser($event, $term)) {
				$event->getServer()->doPrivmsg(
					$event->getSource(), "No cheating!"
				);
				return;
			}

			if ($action == '++') {
				$karma = $this->incKarma($term);
				$response = array_rand_value($this->incResponses);
			} else {
				$karma = $this->decKarma($term);
				$response = array_rand_value($this->decResponses);
			}

			$event->getServer()->doPrivmsg(
				$event->getSource(), "{$term} $response (Karma: {$karma})"
			);
		}
	}

	public function cmdKarma($event, $term = null)
	{
		if ($term) {
			$nterm = $this->normalizeTerm($term);
			$karma = Bot::memory()->get("karma.terms[$nterm]");
			if (!$karma) {
				$karma = 0;
			}

			$event->getServer()->doPrivmsg(
				$event->getSource(), "{$term} has {$karma} karma."
			);
			return;
		}

		if (empty($this->score)) {
			$response = "Sadly, the cosmic karma pool is empty.";
		} else {
			$i=$this->limit;
			$tmp = array();
			foreach($this->score as $term => $karma) {
				$tmp[($i-->0?'Highest':'Lowest')][] = "$term($karma)";
			}

			$response = array('Karma scores:');
			foreach($tmp as $key => &$list) {
				$response[] = $key .': '.
				implode(', ', ($key=='Lowest' ? array_reverse($list) : $list));
			}
		}

		$event->getServer()->doPrivmsg(
			$event->getSource(), $response
		);
	}

	protected function incKarma($term)
	{
		$karma = Bot::memory()->inc("karma.terms[$term]");
		$this->updateScore($term, $karma);
		return $karma;
	}

	protected function decKarma($term)
	{
		$karma = Bot::memory()->dec("karma.terms[$term]");
		$this->updateScore($term, $karma);
		return $karma;
	}

	protected function updateScore($term, $karma)
	{
		$c = count($this->score);
/*		if ($c == 10) {
			
		}
*/
		$this->score[$term] = $karma;
		arsort($this->score);
		if ($c > $this->max) {
			$this->score = array_merge(
				array_slice($this->score, 0, $this->limit, true),
				array_slice($this->score, -$this->limit, $this->limit, true)
			);
		}
	}

	protected function normalizeTerm($term)
	{
		$term = strtolower($term);
		return $term;
	}

	/**
	 * @todo Check the users hostmask and alternate nicks
	 */
	protected function termMatchesUser($event, $term)
	{
		if ($term == strtolower($event->getNick())) {
			return true;
		}

		return false;
	}
}
