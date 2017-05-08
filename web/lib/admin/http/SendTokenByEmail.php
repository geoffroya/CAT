<?php
namespace web\lib\admin\http;

use core\common\OutsideComm;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SendTokenByEmail extends AbstractInvokerCommand{
    
    const COMMAND = "sendtokenbyemail";
    
    /**
     * 
     * @var PHPMailer
     */
    protected $mail = null;
    
    /**
     * 
     * @var GetTokenEmailDetails
     */
    protected $detailsCommand = null;
    
    /**
     *
     * @var SilverbulletContext
     */
    protected $context;
    
    /**
     * 
     * @param string $commandToken
     * @param SilverbulletContext $context
     */
    public function __construct($commandToken, $context){
        parent::__construct($commandToken, $context);
        $this->mail = OutsideComm::mailHandle();
        $this->detailsCommand = new GetTokenEmailDetails(GetTokenEmailDetails::COMMAND, $context);
        $this->context = $context;
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\http\AbstractCommand::execute()
     */
    public function execute() {
        if(isset($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]) && isset($_POST[ValidateEmailAddress::PARAM_ADDRESS])){
            
            $invitationToken = $this->parseString($_POST[GetTokenEmailDetails::PARAM_TOKENLINK]);
            $address = $this->parseString($_POST[ValidateEmailAddress::PARAM_ADDRESS]);
            
            $this->mail->Subject  = $this->detailsCommand->getSubject();
            $this->mail->Body = $this->detailsCommand->getBody($invitationToken);
            
            $this->mail->addAddress($address);
            if($this->mail->send()) {
                $this->storeInfoMessage(sprintf(_("Email message has been sent successfuly to '%s'!"), $address));
            } else {
                $this->storeErrorMessage(sprintf(_("Email message could not be sent to '%s'. Mailer error: '%s'."), $address, $this->mail->ErrorInfo));
            }
            $this->context->redirectAfterSubmit();
        }
    }
}