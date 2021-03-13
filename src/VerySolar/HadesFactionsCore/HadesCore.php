<?php

declare(strict_types=1);

namespace VerySolar\HadesFactionsCore;

//EconomyAPI Issues
use onebone\economyapi\EconomyAPI;
//FormAPI Issues
use jojoe77777\FormAPI\SimpleForm;
//CustomEnchant Issues
use DaPigGuy\PiggyCustomEnchants\CustomEnchantManager;
use DaPigGuy\PiggyCustomEnchants\enchants\CustomEnchant;
use DaPigGuy\PiggyCustomEnchants\PiggyCustomEnchants;
//PocketMine Issues
use pocketmine\command\CommandExecutor;
use pocketmine\Server;
use pocketmine\plugin\PluginManager;
use pocketmine\entity\EffectInstance;
use pocketmine\command\ConsoleCommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\utils\TextFormat as TF;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\nbt\tag\{CompoundTag, IntTag, StringTag, IntArrayTag};
use pocketmine\tile\Tile;
use pocketmine\permission\Permission;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerItemConsumeEvent;
use pocketmine\entity\Effect;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\entity\Zombie;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\math\Vector3;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\scheduler\CallbackTask;
use pocketmine\level\Position;
use pocketmine\level\Level;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\level\particle\HugeExplodeParticle;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\math\Vector2;
use pocketmine\utils\Config;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\tile\Chest;
use pocketmine\block\Block;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\inventory\ChestInventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Armor;
use pocketmine\entity\Creature;
use pocketmine\level\particle\HappyVillagerParticle;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\entity\Human;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\object\ItemEntity;
use pocketmine\scheduler\ClosureTask;
use function array_map;
use function in_array;
use function is_array;
use function is_numeric;
use function str_replace;
use function strtolower;
use pocketmine\item\Tool;
use function array_fill;
use function array_merge;
use function array_rand;
use function is_null;
use function mt_rand;
use function array_search;
use function sendFullPlayerListData;

class HadesCore extends PluginBase implements Listener {
	
	/** @var string[] */
	private $enabledWorlds = [];
	/** @var string[] */
	private $disabledWorlds = [];
	/** @var bool */
	private $useDefaultWorld = false;

	private $sell;
	
    public function onEnable() : void
    {
        $this->getLogger()->info("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8) Enabled");
		$this->getLogger()->info("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8) This core was made by VerySolar");
		$this->enabledWorlds = $this->getConfig()->get("enabled-worlds");
		$this->disabledWorlds = $this->getConfig()->get("disabled-worlds");
		$this->useDefaultWorld = $this->getConfig()->get("use-default-world");
	}
	
	public function onBreak(BlockBreakEvent $event) {
		if ($event->isCancelled()) {
			return;
		}
		$event->getPlayer()->addXp($event->getXpDropAmount());
		$event->setXpDropAmount(0);
	}

	public function onPlayerKill(PlayerDeathEvent $event) {
		$player = $event->getPlayer();
		$cause = $player->getLastDamageCause();
		if ($cause instanceof EntityDamageByEntityEvent) {
			$damager = $cause->getDamager();
			if ($damager instanceof Player) {
				$damager->addXp($player->getXpDropAmount());
				$player->setCurrentTotalXp(0);
			}
		}
	}
	public function onDamage(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof Player){
			return;
		}
		if($event->getCause() === EntityDamageEvent::CAUSE_VOID){
			if($this->saveFromVoidAllowed($entity->getLevel())){
				$this->savePlayerFromVoid($entity);
				$event->setCancelled();
			}
		}
	}

	private function saveFromVoidAllowed(Level $level) : bool {
		if(empty($this->enabledWorlds) and empty($this->disabledWorlds)){
			return true;
		}
		$levelFolderName = $level->getFolderName();

		if(in_array($levelFolderName, $this->disabledWorlds)){
			return false;
		}
		if(in_array($levelFolderName, $this->enabledWorlds)){
			return true;
		}
		if(!empty($this->enabledWorlds) and !in_array($levelFolderName, $this->enabledWorlds)){
			return false;
		}
		return true;
	}

	private function savePlayerFromVoid(Player $player) : void{
		if($this->useDefaultWorld){
			$position = $player->getServer()->getDefaultLevel()->getSpawnLocation();
		} else {
			$position = $player->getLevel()->getSpawnLocation();
		}
		$player->teleport($position);
	}
	
    public function e_damage(EntityDamageEvent $event){
		if($event->getCause()===EntityDamageEvent::CAUSE_FALL)
			$event->setCancelled();
	}
	
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		switch($command->getName()){
			case "gms":
			if ($sender->hasPermission("core.gms.command")) {
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Gamemode S");
		    	$sender->setGamemode(0);
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
                $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
				return true;
			}
		}
		
		switch($command->getName()){
			case "gmc":
			if ($sender->hasPermission("core.gmc.command")) {
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Gamemode C");
		    	$sender->setGamemode(1);
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
                $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
				return true;
			}
		}

		switch($command->getName()){
			case "spectate":
			if ($sender->hasPermission("core.spectate.command")) {
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Spectating Mode");
		    	$sender->setGamemode(3);
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
				return true;
			}
		}
		
		switch($command->getName()){
			case "spec":
			if ($sender->hasPermission("core.spectate.command")) {
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Spectating Mode");
		    	$sender->setGamemode(3);
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
				return true;
			}
		}
		
		switch($command->getName()){
			case "admin":
			if ($sender->hasPermission("core.admin.command")) {
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
				$sender->getLevel()->addSound(new EndermanTeleportSound($sender));
				$sender->setOp(true);
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Admin Mode!");
				return true;
			}
		}

		switch($command->getName()){
			case "player":
			if ($sender->hasPermission("core.player.command")) {
                $sender->getLevel()->addParticle(new HappyVillagerParticle($sender));
				$sender->getLevel()->addSound(new EndermanTeleportSound($sender));
				$sender->setOp(false);
				$sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a You are now in Player Mode!");
				return true;
			}
		}
		
        switch($command->getName()){
            case "feed":
                if($sender instanceof Player){
                    if($sender->hasPermission("core.feed.command")){
                        $sender->setFood(20);
						$sender->setSaturation(20);
                        $sender->getLevel()->addParticle(new HeartParticle($sender));
                        $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
                        $sender->sendPopup("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a Success! You've been fed!");
					}
					break;
					
				}
				return true;
		}
		
		switch($command->getName()){
          case "heal":
          if($command == "heal"){
               if($sender->hasPermission("core.heal.command") && $sender instanceof Player) {
                    $sender->setHealth($sender->getMaxHealth());
                    $sender->sendPopup(TF::YELLOW."§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a Success! You've been healed!");
					$sender->getLevel()->addParticle(new HeartParticle($sender));
					$sender->getLevel()->addSound(new EndermanTeleportSound($sender));
               }
               if(isset($args[0])){
                    if($sender->hasPermission("core.heal.other.command")){
                      $player = $this->getServer()->getPlayer($args[0]);
                      if($player !== null){
                          $player->setHealth($sender->getMaxHealth());
						  $sender->sendPopup(TF::YELLOW. "$args[0] has been healed");
						  $sender->getLevel()->addParticle(new HeartParticle($sender));
						  $sender->getLevel()->addSound(new EndermanTeleportSound($sender));
						  $player->sendPopup(TF::YELLOW. "§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§a Success! You have been healed by ". $sender->getName());
                      }else{
						  $sender->sendPopup(TF::RED. "§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§c Oops, player is not online!");
					  }
					}
			   }
          return true;
		  }
		}
		
		switch($command->getName()){
			case "day":
				if ($sender->hasPermission("core.cmd.day")){
				if ($sender instanceof Player){
					$level = $sender->getLevel();
					$level->setTime(0);
					$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 Time Set to Day!");
					return true;
				}
				$level = $this->getServer()->getDefaultLevel();
				$level->setTime(0);
				$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 Time Set to Day!");
				return true;
				}
				$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 You don't have permission for this command");
		}
		
		switch($command->getName()){
			case "night":
				if ($sender->hasPermission("core.cmd.night")){
				if ($sender instanceof Player){
					$level = $sender->getLevel();
					$level->setTime(14000);
					$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 Time Set to Night!");
					return true;
				}
				$level = $this->getServer()->getDefaultLevel();
				$level->setTime(14000);
				$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 Time Set to Night!");
				return true;
				}
				$sender->sendMessage("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 You don't have permission for this command");
		}
						
	    switch($command->getName()){
	    case "bless":
        if (strtolower($command->getName()) == "bless");
            if ($sender->hasPermission("core.bless.command")) {
                foreach ($sender->getEffects() as $effect) {
                    if ($effect->getType()->isBad()) {
                        $sender->removeEffect($effect->getId());
                        $sender->sendMessage("§b*§d*§b*§e Blessed §b*§d*§b*§a");
                    }elseif(!$sender->hasPermission("core.bless.command")){
                        $sender->sendMessage(" §cYou require §fcore.bless.command§c to use this Command!");
					}
				}
			}
	return false;
		}
		
        switch($command->getName()){     
            case "item":
$item = $sender->getInventory()->getItemInHand();
$pname = $sender->getName();
$count = $item->getCount();
$name = $item->getName();     
                if ($sender->hasPermission("core.brag.command")){
                	$this->broadcastMsg("§l§f[§d+§f]§r§8 (§bHadesFactionsCore§8)§7 §f".$pname." is §bbragging §8[§c".$name." §f".$count."§7x§8]");
                }else{     
                     $sender->sendMessage(TextFormat::RED . "You dont have permission!");
                     return true;
                }    
                break;
            
         }  
        return true;                         
	}
	
	public function broadcastMsg($msg){
		$this->getServer()->broadcastMessage($msg);
	}
}