<?php namespace Net;

require_once(PATH_SYSTEM."/modules/net/vendor/phpmailer/class.phpmailer.php");
use PHPMailer;

use Phpr\SystemException;

/**
 * Class for handling creating outgoing socket requests
 * @package PHPR
 */
class Email 
{
	protected $options;
    protected $mailer;

	/**
	 * Constructor
	 * @param array $params Parameters
	 *  - set_default: optional (default true). Sets default options as in {@link set_defaults}
	 */
	public function __construct($params = array('set_default' => true)) 
	{
		$this->reset_mailer();

		if (isset($params['set_default']) && $params['set_default'])
			$this->set_default();
	}	
	
	/**
	 * Static constructor
	 * @param array $params Parameters
	 * @return object Net_Email
	 */
	public static function create($params = array('set_default' => true)) 
	{
		return new self($params);
	}

	/**
	 * Applies default Mailer options
	 */	
	public function set_default() 
	{
        $this->set_mode_mail();        
        $this->reset_recipients();        
		$this->reset_reply_to();        
	}

    public function reset_mailer()
    {
        $mail = new PHPMailer();
        $mail->Encoding = "8bit";
        $mail->CharSet = "utf-8";
        $mail->WordWrap = 0;
        $this->mailer = $mail;        
    }

	/**
	 * Sets multiple request options
	 * @param array $options CURL options
	 */
	public function set_options($options) 
	{
		foreach ($options as $key => $value)
		{
			$this->options[$key] = $value;
		}
		return $this;
	}

	public function send()
	{
        extract($this->options);

        $mail = $this->mailer;
        $mail->From = $sender_email;
        $mail->FromName = $sender_name;
        $mail->Sender = $sender_email;
        $mail->Subject = $subject;
        $mail->IsHTML(true);

        if (isset($reply_to) && is_array($reply_to)) {
            foreach ($reply_to as $address=>$name) {
                $mail->AddReplyTo($address, $name);
            }
        }
        
        if (isset($attachments) && is_array($attachments)) {
            foreach ($attachments as $file_path=>$file_name) {
                $mail->AddAttachment($file_path, $file_name);
            }
        }

        $mail->ClearAddresses();

        $external_recipients = array();

        foreach ($recipients as $recipient=>$email) {
            if (is_object($email) && isset($email->email)) {
                $mail->AddAddress($email->email, $email->name);
                $external_recipients[$email->email] = $email->name;
            }
            else {
                $mail->AddAddress($email, $recipient);
                $external_recipients[$email] = $recipient;
            }
        }
        
        // Basic content parsing
        $html_body = $content;
        $html_body = str_replace('{recipient_email}', implode(', ', array_keys($external_recipients)), $html_body);     
        $text_body = trim(strip_tags(preg_replace('|\<style\s*[^\>]*\>[^\<]*\</style\>|m', '', $html_body)));
        
        $mail->Body = $html_body;
        $mail->AltBody = $text_body;

        if (!$mail->Send())
            throw new SystemException('Error sending message '.$subject.': '.$mail->ErrorInfo);

	}

    public function set_subject($subject)
    {
        $this->options['subject'] = $subject;
        return $this;
    }

    public function set_content($content)
    {
        $this->options['content'] = $content;
        return $this;
    }

    // Attachments
    // 

    public function reset_attachments()
    {
        $this->options['attachments'] = array();
        return $this;
    }    

    public function add_attachment($file_path, $file_name)
    {
        $this->options['attachments'][] = array($file_path => $file_name);
        return $this;
    }

    // Sender / Reply to
    // 

    public function reset_reply_to()
    {
        $this->options['reply_to'] = array();
        return $this;
    }    

    public function add_reply_to($email, $name = null)
    {
        $this->options['reply_to'][] = array($email => $name);
        return $this;
    }

    public function set_sender($email, $name = null)
    {
        $this->options['sender_email'] = $email;
        $this->options['sender_name'] = $name;
        return $this;
    }

    // Send modes
    // 

	public function set_mode_mail() 
	{
		$this->mailer->IsMail();
        return $this;		
	}

	public function set_mode_smtp($host, $port, $secure = false, $user = null, $password = null)
	{

        $this->mailer->Port = strlen($port) ? $port : 25;
        if ($secure)
            $this->mailer->SMTPSecure= 'ssl';

        $this->mailer->Host = $host;
        if ($user !== null && $password !== null) {
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $user;
            $this->mailer->Password = $password;
        } else {
            $this->mailer->SMTPAuth = false;
        }

        $this->mailer->IsSMTP();
        return $this;
	}

    public function set_mode_sendmail($path) 
    {
        $this->mailer->IsSendmail();
        $this->mailer->Sendmail = $this->fix_sendmail_path($path);
        return $this;
    }

    protected function fix_sendmail_path($value)
    {
        if (!strlen($value))
            $value = '/usr/sbin/sendmail';

        if (substr($value, -1) == '/')
            $value = substr($value, 0, -1);

        if (substr($value, -9) != '/sendmail')
            $value .= '/sendmail';

        return $value;
    }    


    // Recipient handling
    // 

    public function add_recipient($recipient)
    {
        $this->options['recipients'][] = $recipient;
        return $this;
    }

    public function add_recipients($recipients)
    {
        if (!is_array($recipients))
            $this->add_recipient($recipients);

        foreach ($recipients as $recipient)
            $this->add_recipient($recipient);

        return $this;
    }

    public function reset_recipients()
    {
        $this->options['recipients'] = array();
        return $this;
    }

    public function get_recipients()
    {
        return $this->options['recipients'];
    }    

}