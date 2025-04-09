<?php

declare( strict_types=1 );

namespace MediaWiki\Skins\Citizen\Components;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MessageLocalizer;

/**
 * CitizenComponentUserInfo component
 */
class CitizenComponentUserInfo implements CitizenComponent {

	public function __construct(
		private bool $isRegistered,
		private bool $isTemp,
		private MediaWikiServices $services,
		private MessageLocalizer $localizer,
		private Title $title,
		private User $user,
		private array $userPageData,
	) {
	}

	/**
	 * Get the user edit count
	 */
	private function getUserEditCount(): ?array {
		// Return user edits
		$edits = $this->services->getUserEditTracker()->getUserEditCount( $this->user );

		if ( !$edits ) {
			return null;
		}

		$edits = number_format( $edits, 0 );
		$label = $this->localizer->msg( 'citizen-sitestats-edits-label' )->text();

		return [
			'count' => $edits,
			'label' => $label
		];
	}

	/**
	 * Build the template data for the user groups
	 */
	private function getUserGroups(): ?array {
		$groups = $this->services->getUserGroupManager()->getUserGroups( $this->user );

		if ( !$groups ) {
			return null;
		}

		$listItems = [];
		$msgKey = 'group-%s-member';
		foreach ( $groups as $group ) {
			$id = sprintf( $msgKey, $group );
			$text = $this->localizer->msg( $id )->text();
			try {
				$title = $this->title->newFromTextThrow( $text, NS_PROJECT );
			} catch ( MalformedTitleException $e ) {
				// ignore
			}

			if ( !$text || !$title ) {
				continue;
			}

			$link = new CitizenComponentLink(
				$title->getLinkURL(),
				ucfirst( $text )
			);

			$listItem = new CitizenComponentMenuListItem( $link, 'citizen-userInfo-usergroup', $id );

			$listItems[] = $listItem->getTemplateData();
		}

		return [
			'array-list-items' => $listItems
		];
	}

	/**
	 * Build the template data for the user page menu
	 */
	private function getUserPage(): array {
		$user = $this->user;
		$userPageData = $this->userPageData;

		$htmlItems = $userPageData['html-items'];
		$realname = htmlspecialchars( $user->getRealName(), ENT_QUOTES );
		if ( $realname !== '' ) {
			$username = htmlspecialchars( $user->getName(), ENT_QUOTES );
			$innerHtml = <<<HTML
				<span id="pt-userpage-realname">$realname</span>
				<span id="pt-userpage-username">$username</span>
			HTML;
			// Dirty but it works
			$htmlItems = str_replace(
				">" . $username . "<",
				">" . $innerHtml . "<",
				$userPageData['html-items']
			);
		}

		$menu = new CitizenComponentMenu( [
			'id' => 'citizen-user-menu-userpage',
			'class' => null,
			'label' => null,
			'html-items' => $htmlItems
		] );

		return $menu->getTemplateData();
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$localizer = $this->localizer;
		$data = [];

		if ( $this->isRegistered ) {
			$data = [
				'data-user-page' => $this->getUserPage(),
				'data-user-edit' => $this->getUserEditCount()
			];

			if ( $this->isTemp ) {
				$data['text'] = $localizer->msg( 'citizen-user-info-text-temp' );
			} else {
				$data['data-user-groups'] = $this->getUserGroups();
			}
		} else {
			$data = [
				'title' => $localizer->msg( 'notloggedin' ),
				'text' => $localizer->msg( 'citizen-user-info-text-anon' )
			];
		}

		return $data;
	}
}
