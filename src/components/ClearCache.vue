<template>
  <k-panel-inside class="k-clearcache-view">
    <k-header>Clear cache</k-header>

    <k-box
      class="k-clearcache-intro"
      icon="info"
      theme="info"
      text="Everything listed here is temporary. Kirby creates it again when it is needed."
    />

    <k-section
      v-for="group in groups"
      :key="group.type"
      :buttons="group.buttons"
      :label="group.label"
    >
      <k-table
        :columns="columns"
        :index="false"
        :rows="group.rows"
        empty="Nothing to clear"
      >
        <template #options="{ row }">
          <k-button
            icon="trash"
            size="sm"
            title="Clear"
            @click="clear(group.type, row.name)"
          />
        </template>
      </k-table>
      <k-text :text="group.help" class="k-help k-clearcache-help" />
    </k-section>
  </k-panel-inside>
</template>

<script>
export default {
  props: {
    cache: [Array, Object],
    media: [Array, Object],
    other: [Array, Object],
  },
  data() {
    return {
      stats: null,
      request: 0
    };
  },
  computed: {
    columns() {
      return {
        text: { label: "Name", mobile: true },
        info: { label: "Location" },
        files: { label: "Files", align: "right", width: "1/8" },
        size: { label: "Size", align: "right", width: "1/8", mobile: true }
      };
    },
    groups() {
      return [
        {
          type: "cache",
          label: "Cache",
          help: "The caches of Kirby and your plugins. Each cache is cleared for all domains of your site and for the command line."
        },
        {
          type: "media",
          label: "Media",
          help: "Copies of your files, thumbnails and the assets of the Panel and your plugins. Kirby creates them again when they are requested. Clearing media files also clears the pages cache, so that cached pages don't link to missing thumbnails."
        },
        {
          type: "other",
          label: "Other",
          help: "Kirby 5 and newer keep content locks with the unsaved changes. Old lock files from Kirby 3 and 4 make Kirby ignore these locks. Clearing the failed login attempts unblocks users and IP addresses."
        }
      ].map((group) => {
        const rows = this.rows(group.type);

        return {
          ...group,
          rows,
          buttons: rows.length ? [{ icon: "trash", text: "Clear all", dialog: "clear-cache/" + group.type }] : null
        };
      });
    }
  },
  watch: {
    cache() {
      this.load();
    }
  },
  created() {
    this.load();
  },
  methods: {
    async clear(type, dir) {
      // ask first, blocked users can log in again
      if (dir === "logins") {
        return this.$panel.dialog.open("clear-cache/" + type + "/" + dir);
      }

      await this.$panel.api.post("clear-cache", {
        type: type,
        dir: dir,
      });

      this.$panel.notification.success("Cleared");
      await this.$panel.view.reload();
    },
    async load() {
      const request = ++this.request;

      this.stats = null;

      const stats = await this.$panel.api.get("clear-cache/stats");

      if (request === this.request) {
        this.stats = stats;
      }
    },
    rows(type) {
      const rows = Object.values(this[type]).map((dir) => {
        const stats = this.stats?.[type]?.[dir.name];

        return {
          id: dir.name,
          name: dir.name,
          text: dir.text,
          info: dir.info,
          files: this.stats ? String(stats?.files ?? "–") : "…",
          size: this.stats ? (stats?.niceSize ?? "–") : "…"
        };
      });

      // old lock files only show up when there are some
      return rows.filter((row) => type !== "other" || row.name !== "lock" || (this.stats && row.files !== "0"));
    }
  }
};
</script>

<style>
.k-clearcache-intro {
  margin-bottom: var(--spacing-12);
}
.k-clearcache-help {
  margin-top: var(--spacing-3);
}
</style>
