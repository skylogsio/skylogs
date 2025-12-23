"use client";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";

import { Button, Grid2 as Grid, Stack, Typography, useTheme, alpha } from "@mui/material";
import { useMutation, useQuery } from "@tanstack/react-query";
import { HiOutlinePlusSm } from "react-icons/hi";
import { toast } from "react-toastify";

import type { CreateUpdateModal } from "@/@types/global";
import { ITelegramProxy } from "@/@types/settings/telegram";
import {
  activateTelegramProxy,
  deactivateTelegramProxy,
  getAllTelegramProxies
} from "@/api/setttings/telegram";
import ProxyCard from "@/components/admin-area/ConnectivitySetting/Telegram/ProxyCard";
import ProxyModal from "@/components/admin-area/ConnectivitySetting/Telegram/ProxyModal";
import EmptyList from "@/components/EmptyList";
import { ENDPOINT_CONFIG } from "@/utils/endpointVariants";

export default function TelegramSettings() {
  const router = useRouter();
  const { palette } = useTheme();
  const [modalData, setModalData] = useState<CreateUpdateModal<ITelegramProxy>>(null);
  const [activeProxy, setActiveProxy] = useState<ITelegramProxy["id"] | null>(null);

  const { data, refetch } = useQuery({
    queryKey: ["telegram-proxy"],
    queryFn: () => getAllTelegramProxies()
  });

  const { mutate: activateTelegramProxyMutation, isPending: isActivating } = useMutation({
    mutationFn: (id: ITelegramProxy["id"]) => activateTelegramProxy(id),
    onSuccess: (_, id) => {
      toast.success("Proxy Activated Successfully.");
      setActiveProxy(id);
    }
  });

  const { mutate: deactivateTelegramProxyMutation, isPending: isDeactivating } = useMutation({
    mutationFn: () => deactivateTelegramProxy(),
    onSuccess: () => {
      toast.success("Proxy Deactivated Successfully.");
      setActiveProxy(null);
    }
  });

  function handleRefreshData() {
    refetch();
  }

  function handleChangeProxyActivation(checked: boolean, proxyId: ITelegramProxy["id"]) {
    if (checked) {
      activateTelegramProxyMutation(proxyId);
    } else {
      deactivateTelegramProxyMutation();
    }
  }

  useEffect(() => {
    if (data) {
      const tempProxy = data.find((item) => item.active);
      if (tempProxy) setActiveProxy(tempProxy.id);
    }
  }, [data]);

  const TelegramIcon = ENDPOINT_CONFIG["telegram"].icon;

  return (
    <>
      <Stack spacing={2}>
        {data && data?.length > 0 ? (
          <>
            <Stack direction="row" justifyContent="space-between" alignItems="flex-end">
              <Typography variant="h5" fontSize="1.8rem" fontWeight="700" component="div">
                Telegram Proxies
              </Typography>
              <Button
                startIcon={<HiOutlinePlusSm size="1.3rem" />}
                onClick={() => setModalData("NEW")}
                size="small"
                variant="contained"
                sx={{ paddingRight: "1rem" }}
              >
                Create
              </Button>
            </Stack>
            <Grid
              container
              spacing={2}
              sx={{ backgroundColor: palette.background.paper, padding: 3, borderRadius: "1rem" }}
            >
              {data?.map((item) => (
                <ProxyCard
                  key={item.id}
                  data={item}
                  onEdit={() => setModalData(item)}
                  onAfterDelete={handleRefreshData}
                  checked={activeProxy === item.id}
                  disabled={isActivating || isDeactivating}
                  onChange={(_, checked) => handleChangeProxyActivation(checked, item.id)}
                />
              ))}
            </Grid>
          </>
        ) : (
          <EmptyList
            icon={<TelegramIcon size="5.5rem" color={palette.common.white} />}
            title="No Proxies Configured"
            description="Set up your first Telegram proxy to enable secure and reliable message delivery. Proxies help ensure your notifications reach users even in restricted networks."
            actionLabel="Create First Proxy"
            onAction={() => setModalData("NEW")}
            onBack={router.back}
            gradientColors={[palette.endpoint.telegram, alpha(palette.endpoint.telegram, 0.7)]}
          />
        )}
      </Stack>
      {modalData && (
        <ProxyModal
          open={!!modalData}
          onClose={() => setModalData(null)}
          data={modalData}
          onSubmit={handleRefreshData}
        />
      )}
    </>
  );
}
