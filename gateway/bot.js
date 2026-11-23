// Recebe o /sync pelo gateway do Discord, que não passa pelo Cloudflare
const path = require('node:path');
const { execFile } = require('node:child_process');
const { Client, Events, GatewayIntentBits, MessageFlags } = require('discord.js');

const ROOT = path.join(__dirname, '..');
require('dotenv').config({ path: path.join(ROOT, '.env'), quiet: true });

const PHP = process.env.PHP_BINARY || '/opt/plesk/php/8.4/bin/php';
const FAILED = 'Não foi possível sincronizar agora. Tente novamente mais tarde.';

const client = new Client({ intents: [GatewayIntentBits.Guilds] });

client.once(Events.ClientReady, (ready) => console.log(`Conectado como ${ready.user.tag}`));

client.on(Events.InteractionCreate, async (interaction) => {
  if (!interaction.isChatInputCommand() || interaction.commandName !== 'sync') return;

  try {
    await interaction.deferReply({ flags: MessageFlags.Ephemeral });
  } catch (error) {
    console.error(error);
    return;
  }

  const args = ['artisan', 'discord:answer-sync', interaction.user.id];
  if (interaction.options.getBoolean('todos')) args.push('--todos');

  execFile(PHP, args, { cwd: ROOT, timeout: 120_000 }, (error, stdout, stderr) => {
    if (error) console.error(error, stderr);
    interaction.editReply(error ? FAILED : stdout.trim() || FAILED).catch(console.error);
  });
});

client.login(process.env.DISCORD_BOT_TOKEN);
