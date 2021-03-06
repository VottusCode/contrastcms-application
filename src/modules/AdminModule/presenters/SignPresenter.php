<?php

namespace ContrastCms\Application\AdminModule;

use Nette\Application\UI;
use Nette\ArrayHash;
use Nette\Mail\Message;
use Nette\Mail\SendmailMailer;
use Nette\Security\Passwords;
use Nette\Utils\Random;
use Nette\Utils\Validators;

class SignPresenter extends AdminBasePresenter
{
	public function renderIn()
	{
		$this->template->setFile(__DIR__ . "/../templates/Sign/in.latte");
	}

	protected function createComponentSignInForm()
	{
		$form = new UI\Form();

		$form->addText('username', 'Uživatelské jméno')
			->setRequired("Povinná položka")->getControlPrototype()->addAttributes(array("placeholder" => "Uživatelské jméno"));
		$form->addPassword('password', 'Heslo')
			->setRequired("Povinná položka")->getControlPrototype()->addAttributes(array("placeholder" => "Heslo"));

		$form->addSubmit('send', 'Login');
		$form->onSuccess[] = [$this, 'signInForm'];

		return $form;
	}


	public function signInForm(UI\Form $form)
	{
		try {
			$values = $form->getValues();

			$user = $this->getUser();
			$user->setExpiration('+1 day');
			$user->login($values->username, $values->password);
			$user->getIdentity()->setRoles(array('admin'));
			$user->getIdentity()->login = $values->username;

			$this->redirect('Homepage:');

		} catch (\Nette\Security\AuthenticationException $e) {
			$form->addError($e->getMessage());
		}
	}

	protected function createComponentForgottenPasswordForm()
	{
		$form = new UI\Form;

		$form->addText('username', 'Uživatelské jméno')
			->setRequired("Povinná položka")->getControlPrototype()->addAttributes(array("placeholder" => "Uživatelské jméno"));

		$form->addSubmit('send', 'Submit');
		$form->onSuccess[] = [$this, 'forgottenPasswordForm'];

		return $form;
	}

	public function forgottenPasswordForm(UI\Form $form, ArrayHash $values)
	{
		$row = $this->context->getService("userRepository")->findAll()->where((array)$values)->fetch();

		if ($row && Validators::isEmail($row->email)) {

			$newPassword = Random::generate(10);

			$row->update([
				"password" => Passwords::hash($newPassword)
			]);

			$this->sendPasswordResetLink($row->email, $newPassword);

			$this->flashMessage("E-mail with instructions has been sent to assigned email address.");
		} else {
			$this->flashMessage("User doest not exists or does not have an email! Please contact administrator.");
		}

		$this->redirect("Sign:in");
	}

	protected function sendPasswordResetLink($email, $newPassword)
	{


		$template = $this->getTemplate();
		$template->setFile(__DIR__ . '/../templates/mail_new_password.latte');
		$template->password = $newPassword;

		$mail = new Message();
		$mail->setFrom("info@contrast-cms.cz", "Contrast CMS")
			->setHtmlBody($template)
			->addTo($email)
			->setSubject("Contrast CMS - new password");

		$mailer = new SendmailMailer();
		$mailer->send($mail);
	}

	public function actionLogout()
	{
		$user = $this->getUser();
		$user->logout(true);

		$this->redirect('Homepage:');
	}
}